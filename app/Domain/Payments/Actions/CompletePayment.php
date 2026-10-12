<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Events\PaymentFailed;
use App\Domain\Payments\Events\PaymentSucceeded;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a confirmed payment outcome (from a verified callback, webhook or
 * status lookup). Approved: the order is paid and goes to the kitchen.
 * Declined: the order waits so the guest can try again or pay at the window.
 * Money that arrives for an order already cancelled is refunded at once.
 * Safe to call any number of times: providers retry.
 */
final class CompletePayment
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * @param  array{transaction?: ?string, fee_minor?: ?int}  $details
     */
    public function handle(Payment $payment, bool $approved, ?string $reason = null, array $details = []): void
    {
        $refundLateMoney = false;

        DB::transaction(function () use ($payment, $approved, $reason, $details, &$refundLateMoney): void {
            $payment = Payment::withoutTenancy()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true) || ($payment->status === PaymentStatus::Failed && ! $approved)) {
                return;
            }

            $order = Order::withoutTenancy()->findOrFail($payment->order_id);
            $provider = array_filter([
                'provider_transaction' => $details['transaction'] ?? null,
                'fee_minor' => $details['fee_minor'] ?? null,
            ], fn (mixed $value): bool => $value !== null);

            if (! $approved) {
                $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => $reason ?? 'declined', ...$provider])->save();

                if ($order->payment_status !== PaymentStatus::Paid) {
                    $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();
                }

                DB::afterCommit(fn () => PaymentFailed::dispatch($payment));

                return;
            }

            $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now(), 'failure_reason' => null, ...$provider])->save();
            $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();

            if ($order->status === OrderStatus::PendingPayment) {
                $next = app(PlaceOrder::class)->startingStatus($order->timing, $order->prepare_at, CarbonImmutable::now());
                $this->transition->handle($order, $next, TransitionOrder::SYSTEM, note: 'payment.approved');
            }

            $refundLateMoney = $order->status === OrderStatus::Cancelled;

            DB::afterCommit(fn () => PaymentSucceeded::dispatch($payment));
        });

        if ($refundLateMoney) {
            app(RefundPayment::class)->handle($payment->fresh() ?? $payment, null, 'order_cancelled_before_payment', 'system');
        }
    }
}
