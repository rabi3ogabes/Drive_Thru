<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\GatewayResolver;

/**
 * Online orders the guest never finished paying. After the timeout each one
 * is checked with its provider: paid → it goes to the kitchen; still in
 * progress → asked again later (up to a day, as SADAD settles overnight);
 * otherwise it is cancelled so it never sits in the kitchen's way.
 */
final class ExpireUnpaidOrders
{
    public function __construct(
        private readonly GatewayResolver $gateways,
        private readonly CompletePayment $complete,
        private readonly CancelOrder $cancel,
    ) {}

    /**
     * @return array{paid: int, cancelled: int, waiting: int}
     */
    public function handle(): array
    {
        $counts = ['paid' => 0, 'cancelled' => 0, 'waiting' => 0];

        $orders = Order::withoutTenancy()
            ->with('restaurant')
            ->where('status', OrderStatus::PendingPayment->value)
            ->where('placed_at', '<', now()->subMinutes((int) config('payments.pending_timeout_minutes')))
            ->orderBy('placed_at')
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            $payment = Payment::withoutTenancy()->where('order_id', $order->id)->latest('id')->first();
            $status = $payment === null ? null : $this->gateways->forPayment($payment)?->lookup($payment);
            $payment?->forceFill(['last_checked_at' => now()])->save();

            if ($payment !== null && $status?->isPaid() === true) {
                $this->complete->handle($payment, true, details: ['transaction' => $status->transaction, 'fee_minor' => $status->feeMinor]);
                $counts['paid']++;

                continue;
            }

            if ($status?->isPending() === true && $order->placed_at?->greaterThan(now()->subHours((int) config('payments.pending_max_hours'))) === true) {
                $counts['waiting']++;

                continue;
            }

            try {
                $this->cancel->bySystem($order, 'payment_timeout');
                $counts['cancelled']++;
            } catch (InvalidTransition) {
                // Paid or cancelled on another path meanwhile.
            }
        }

        return $counts;
    }
}
