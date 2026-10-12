<?php

declare(strict_types=1);

namespace App\Domain\Payments\Sadad;

use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\ChargeResult;
use App\Domain\Payments\Data\ProviderStatus;
use App\Domain\Payments\Data\RefundResult;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * SADAD (Qatar), Web Checkout 2.1 plus the merchant API.
 *
 * - Charge: the guest is sent to our page that posts a signed form to SADAD's
 *   hosted checkout (cards, SADAD wallet, Apple Pay and Google Pay there).
 * - Confirmation: SADAD's callback and webhook, both checked with the
 *   checksum (SadadSignature), or a status lookup through the API.
 * - Refunds and lookups: the merchant API with an access token.
 *
 * Test and live use the same URLs; the secret key decides. QAR only.
 */
final class SadadGateway implements PaymentGateway
{
    public const NAME = 'sadad';

    /** SADAD transaction statuses (API: transactionstatusId). */
    private const PAID = [3, 4];

    private const FAILED = [2, 7];

    public function __construct(
        private readonly Integration $integration,
        private readonly ProviderClient $client,
        private readonly SadadSignature $signature,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function mode(): string
    {
        return $this->integration->mode;
    }

    public function methods(): array
    {
        return [PaymentMethod::Card];
    }

    public function charge(Payment $payment, string $returnUrl): ChargeResult
    {
        $reference = 'SDD'.Str::upper(Str::random(13));

        return ChargeResult::redirect($reference, route('payments.sadad.checkout', ['reference' => $reference]));
    }

    /**
     * The signed fields for SADAD's checkout form, built at the moment the
     * guest is sent there.
     *
     * @return array<string, string>
     */
    public function checkoutFields(Payment $payment): array
    {
        $payment->loadMissing(['order.customer', 'order.restaurant']);
        $order = $payment->order;

        $fields = [
            'merchant_id' => (string) $this->integration->credential('merchant_id'),
            'ORDER_ID' => (string) $payment->reference,
            'TXN_AMOUNT' => number_format($payment->amount_minor / 10 ** Money::decimals($payment->currency), 2, '.', ''),
            'WEBSITE' => (string) $this->integration->credential('website'),
            'CALLBACK_URL' => route('payments.sadad.callback'),
            'MOBILE_NO' => ltrim((string) $order->customer->phone, '+'),
            'EMAIL' => (string) ($order->restaurant->contact_email ?: config('mail.from.address')),
            'txnDate' => CarbonImmutable::now('Asia/Qatar')->format('Y-m-d H:i:s'),
        ];

        $fields['signature'] = $this->signature->sign($fields, $this->secretKey());

        return $fields;
    }

    public function checkoutUrl(): string
    {
        return (string) config('payments.sadad.checkout_url');
    }

    /** The key that signs requests (for the current mode). */
    public function secretKey(): string
    {
        return $this->secretKeyFor($this->integration->mode);
    }

    /** The key for a given mode: a payment is always verified with the key it was made with. */
    public function secretKeyFor(string $mode): string
    {
        return (string) $this->integration->credential($mode === Integration::LIVE ? 'secret_key_live' : 'secret_key_test');
    }

    public function refund(Payment $payment, int $amountMinor, ?string $reason = null): RefundResult
    {
        if ($payment->provider_transaction === null) {
            return RefundResult::failed('no_transaction');
        }

        try {
            $response = $this->client->call(self::NAME, 'refund', $payment->restaurant_id, fn ($http) => $http
                ->withHeaders(['Authorization' => $this->accessToken()])
                ->acceptJson()
                ->post($this->api('/transactions/refundTransaction'), [
                    (string) config('payments.sadad.refund_field') => $payment->provider_transaction,
                    'amount' => (float) number_format($amountMinor / 10 ** Money::decimals($payment->currency), 2, '.', ''),
                ]));
        } catch (ProviderUnavailable) {
            return RefundResult::failed('unreachable');
        }

        if (! $response->successful()) {
            return RefundResult::failed(Str::limit((string) ($response->json('message') ?? 'http_'.$response->status()), 180));
        }

        return RefundResult::succeeded((string) ($response->json('invoicenumber') ?? $payment->provider_transaction));
    }

    public function supportsPartialRefunds(): bool
    {
        return (bool) $this->integration->option('partial_refunds', config('payments.sadad.partial_refunds'));
    }

    public function lookup(Payment $payment): ProviderStatus
    {
        if ($payment->provider_transaction === null) {
            return ProviderStatus::unknown();
        }

        try {
            $response = $this->client->call(self::NAME, 'lookup', $payment->restaurant_id, fn ($http) => $http
                ->withHeaders(['Authorization' => $this->accessToken()])
                ->acceptJson()
                ->get($this->api('/transactions/getTransaction'), ['transactionno' => $payment->provider_transaction]));
        } catch (ProviderUnavailable) {
            return ProviderStatus::unknown();
        }

        if (! $response->successful()) {
            return ProviderStatus::unknown();
        }

        $status = (int) $response->json('transactionstatusId');
        $decimals = 10 ** Money::decimals($payment->currency);
        $amount = $response->json('amount');
        $fee = $response->json('servicecharge');

        return new ProviderStatus(
            state: match (true) {
                in_array($status, self::PAID, true) => ProviderStatus::PAID,
                in_array($status, self::FAILED, true) => ProviderStatus::FAILED,
                default => ProviderStatus::PENDING,
            },
            transaction: $payment->provider_transaction,
            amountMinor: is_numeric($amount) ? (int) round((float) $amount * $decimals) : null,
            feeMinor: is_numeric($fee) ? (int) round((float) $fee * $decimals) : null,
        );
    }

    /** Signs in to the merchant API (used by "Test connection" in settings). */
    public function verifyConnection(): bool
    {
        Cache::forget($this->tokenKey());

        try {
            return $this->accessToken() !== '';
        } catch (ProviderUnavailable) {
            return false;
        }
    }

    /**
     * @throws ProviderUnavailable
     */
    private function accessToken(): string
    {
        // Kept encrypted in the cache: the store is the database outside production.
        return Crypt::decryptString(Cache::remember($this->tokenKey(), now()->addMinutes(50), function (): string {
            $response = $this->client->call(self::NAME, 'login', $this->integration->restaurant_id, fn ($http) => $http
                ->acceptJson()
                ->post($this->api('/userbusinesses/login'), [
                    'sadadId' => (int) $this->integration->credential('api_sadad_id'),
                    'secretKey' => (string) $this->integration->credential($this->integration->isLive() ? 'api_key_live' : 'api_key_test'),
                    'domain' => (string) $this->integration->credential('api_domain'),
                ]));

            $token = $response->json('accessToken');

            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw ProviderUnavailable::because(self::NAME, 'login_failed');
            }

            return Crypt::encryptString($token);
        }));
    }

    private function tokenKey(): string
    {
        return 'sadad.token.'.$this->integration->id.'.'.$this->integration->mode;
    }

    private function api(string $path): string
    {
        return rtrim((string) config('payments.sadad.api.'.($this->integration->isLive() ? 'live' : 'test')), '/').$path;
    }
}
