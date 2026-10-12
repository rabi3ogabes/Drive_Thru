<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\ChargeResult;
use App\Domain\Payments\Data\ProviderStatus;
use App\Domain\Payments\Data\RefundResult;
use Illuminate\Support\Str;

/**
 * A safe stand-in for a real provider, available outside production. Wallets
 * (Apple Pay, Google Pay) are approved instantly; cards go through a simulated
 * 3-D Secure page where the guest can approve or decline. Refunds always work.
 */
final class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function mode(): string
    {
        return 'test';
    }

    public function methods(): array
    {
        return [PaymentMethod::Card, PaymentMethod::ApplePay, PaymentMethod::GooglePay];
    }

    public function charge(Payment $payment, string $returnUrl): ChargeResult
    {
        $reference = 'FAKE-'.Str::upper(Str::random(10));

        if ($payment->method === PaymentMethod::Card) {
            return ChargeResult::redirect($reference, route('payments.fake', ['reference' => $reference, 'return' => $returnUrl]));
        }

        return ChargeResult::paid($reference);
    }

    public function refund(Payment $payment, int $amountMinor, ?string $reason = null): RefundResult
    {
        return RefundResult::succeeded('FAKE-R-'.Str::upper(Str::random(8)));
    }

    public function supportsPartialRefunds(): bool
    {
        return true;
    }

    public function lookup(Payment $payment): ProviderStatus
    {
        return match ($payment->status) {
            PaymentStatus::Paid, PaymentStatus::Refunded => new ProviderStatus(ProviderStatus::PAID, $payment->reference, $payment->amount_minor),
            PaymentStatus::Failed => new ProviderStatus(ProviderStatus::FAILED, $payment->reference),
            default => new ProviderStatus(ProviderStatus::PENDING, $payment->reference),
        };
    }
}
