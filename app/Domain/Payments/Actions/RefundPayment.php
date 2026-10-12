<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Events\PaymentRefunded;
use App\Domain\Payments\GatewayResolver;
use App\Domain\Payments\Models\Refund;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gives money back through the payment's provider: the whole remaining
 * amount, or part of it when the provider supports partial refunds. The
 * refund row is written first (so nothing is lost if the provider times
 * out), then completed with the provider's answer. A failed refund is kept
 * and announced so staff can follow up; the order is never left in limbo.
 */
final class RefundPayment
{
    public function __construct(private readonly GatewayResolver $gateways) {}

    /**
     * @param  ?int  $amountMinor  null = everything that can still be refunded
     * @param  string  $requestedBy  "staff:12", "device:3", "customer:5" or "system"
     */
    public function handle(Payment $payment, ?int $amountMinor, string $reason, string $requestedBy): Refund
    {
        $refund = DB::transaction(function () use ($payment, $amountMinor, $reason, $requestedBy): Refund {
            $payment = Payment::withoutTenancy()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true) || $payment->refundableMinor() <= 0) {
                throw new InvalidArgumentException('Nothing to refund on this payment.');
            }

            $amount = $amountMinor ?? $payment->refundableMinor();
            $gateway = $this->gateways->forPayment($payment);

            if ($amount <= 0 || $amount > $payment->refundableMinor()) {
                throw new InvalidArgumentException('Refund amount is out of range.');
            }

            if ($amount < $payment->refundableMinor() && $gateway !== null && ! $gateway->supportsPartialRefunds()) {
                throw new InvalidArgumentException('This provider only refunds the full amount.');
            }

            // Reserve the amount now so two refunds can't both pass the check.
            $payment->forceFill(['refunded_minor' => $payment->refunded_minor + $amount])->save();

            return Refund::withoutTenancy()->create([
                'restaurant_id' => $payment->restaurant_id,
                'payment_id' => $payment->id,
                'amount_minor' => $amount,
                'status' => Refund::PENDING,
                'reason' => mb_substr($reason, 0, 200),
                'requested_by' => $requestedBy,
            ]);
        });

        $payment = $payment->fresh() ?? $payment;
        $gateway = $this->gateways->forPayment($payment);
        $result = $gateway?->refund($payment, $refund->amount_minor, $reason);

        DB::transaction(function () use ($payment, $refund, $result): void {
            $payment = Payment::withoutTenancy()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($result === null || ! $result->succeeded) {
                // Give the reserved amount back so staff can try again.
                $payment->forceFill(['refunded_minor' => max(0, $payment->refunded_minor - $refund->amount_minor)])->save();
                $refund->forceFill(['status' => Refund::FAILED, 'failure_reason' => $result->failureReason ?? 'no_gateway'])->save();

                return;
            }

            $refund->forceFill(['status' => Refund::SUCCEEDED, 'provider_reference' => $result->reference])->save();
            $fully = $payment->refunded_minor >= $payment->amount_minor;
            $payment->forceFill(['refunded_at' => now(), 'status' => $fully ? PaymentStatus::Refunded : $payment->status])->save();

            if ($fully) {
                Order::withoutTenancy()->whereKey($payment->order_id)->update(['payment_status' => PaymentStatus::Refunded->value]);
            }
        });

        $refund->refresh();

        activity()->performedOn($payment)->event('payment.refund')
            ->withProperties(['amount_minor' => $refund->amount_minor, 'status' => $refund->status, 'reason' => $reason, 'by' => $requestedBy])
            ->log('payment.refund');

        PaymentRefunded::dispatch($refund);

        return $refund;
    }
}
