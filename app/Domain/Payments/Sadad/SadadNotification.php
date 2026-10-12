<?php

declare(strict_types=1);

namespace App\Domain\Payments\Sadad;

use App\Domain\Integrations\Models\ProviderCall;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Actions\CompletePayment;
use App\Domain\Payments\GatewayResolver;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

/**
 * Applies what SADAD tells us about a payment, from the browser callback
 * (form fields) or the server webhook (JSON). Nothing is trusted until the
 * checksum matches with the key the payment was made with, the amount
 * matches, and (for webhooks) test/live matches. Status 3 = paid, 2 = failed,
 * 1 = in progress (kept pending; SADAD settles these after midnight).
 */
final class SadadNotification
{
    public function __construct(
        private readonly GatewayResolver $gateways,
        private readonly SadadSignature $signature,
        private readonly CompletePayment $complete,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return ?Payment the payment it was about, when authentic
     */
    public function apply(array $params, string $source): ?Payment
    {
        $reference = $params['ORDERID'] ?? $params['websiteRefNo'] ?? null;
        $transaction = $params['transaction_number'] ?? $params['transactionNumber'] ?? null;
        $status = (int) ($params['transaction_status'] ?? $params['transactionStatus'] ?? 0);
        $amount = $params['TXNAMOUNT'] ?? $params['txnAmount'] ?? null;

        $payment = is_string($reference) && $reference !== ''
            ? Payment::withoutTenancy()->with('order.restaurant')->where('reference', $reference)->where('gateway', SadadGateway::NAME)->first()
            : null;

        if ($payment === null) {
            $this->reject(null, $source, 'unknown_order');

            return null;
        }

        $gateway = $this->gateways->for($payment->restaurant_id);

        if (! $gateway instanceof SadadGateway || ! $this->signature->verify($params, $gateway->secretKeyFor($payment->mode))) {
            $this->reject($payment, $source, 'invalid_checksum');

            return null;
        }

        if (array_key_exists('isTestMode', $params) && ((string) $params['isTestMode'] === '1') !== ($payment->mode === 'test')) {
            $this->reject($payment, $source, 'mode_mismatch');

            return null;
        }

        if ($amount !== null && is_numeric($amount) && (int) round((float) $amount * 10 ** Money::decimals($payment->currency)) !== $payment->amount_minor) {
            $this->reject($payment, $source, 'amount_mismatch');

            return null;
        }

        $details = ['transaction' => is_scalar($transaction) && (string) $transaction !== '' ? (string) $transaction : null];

        match ($status) {
            3 => $this->complete->handle($payment, true, details: $details),
            2 => $this->complete->handle($payment, false, mb_substr((string) ($params['RESPMSG'] ?? $params['message'] ?? 'declined'), 0, 180), $details),
            default => $payment->forceFill(['provider_transaction' => $details['transaction'] ?? $payment->provider_transaction, 'last_checked_at' => now()])->save(),
        };

        $this->record($payment, $source, true, null);

        return $payment->fresh();
    }

    private function reject(?Payment $payment, string $source, string $why): void
    {
        $this->record($payment, $source, false, $why);
    }

    private function record(?Payment $payment, string $source, bool $ok, ?string $error): void
    {
        if ($payment === null) {
            Log::warning('SADAD notification for an unknown order', ['source' => $source, 'error' => $error]);

            return;
        }

        ProviderCall::withoutTenancy()->create([
            'restaurant_id' => $payment->restaurant_id,
            'provider' => SadadGateway::NAME,
            'operation' => $source,
            'ok' => $ok,
            'error' => $error,
            'created_at' => now(),
        ]);
    }
}
