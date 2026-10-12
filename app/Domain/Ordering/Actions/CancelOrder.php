<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Exceptions\OrderRejected;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Actions\RefundPayment;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an order: by the guest within the restaurant's free-cancellation
 * window (before the kitchen starts), or by the system for an unpaid order.
 * Paid amounts are refunded and stock goes back on sale.
 */
final class CancelOrder
{
    public function __construct(
        private readonly TransitionOrder $transition,
        private readonly BranchStock $stock,
        private readonly RefundPayment $refunds,
    ) {}

    public function canCustomerCancel(Order $order): bool
    {
        if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Scheduled, OrderStatus::Received], true)) {
            return false;
        }

        $window = $order->restaurant->cancellation_window_minutes;

        return $order->placed_at !== null && $order->placed_at->addMinutes($window)->isFuture();
    }

    /**
     * @throws OrderRejected
     */
    public function byCustomer(Order $order, ?string $reason = null): Order
    {
        $order->loadMissing('restaurant');

        if (! $this->canCustomerCancel($order)) {
            throw OrderRejected::because('cancel_window');
        }

        return $this->cancel($order, TransitionOrder::CUSTOMER, $order->customer_id, $reason ?? 'customer');
    }

    /** Staff or a branch screen cancels, with a reason, from any status that allows it. */
    public function byStaff(Order $order, string $actorType, int $actorId, string $reason): Order
    {
        return $this->cancel($order, $actorType, $actorId, $reason);
    }

    public function bySystem(Order $order, string $reason): Order
    {
        return $this->cancel($order, TransitionOrder::SYSTEM, null, $reason);
    }

    private function cancel(Order $order, string $actorType, ?int $actorId, string $reason): Order
    {
        $order = DB::transaction(function () use ($order, $actorType, $actorId, $reason): Order {
            $this->transition->handle($order, OrderStatus::Cancelled, $actorType, $actorId, $reason);

            // A payment still waiting at the provider stops here; if the money arrives later it is refunded then.
            Payment::withoutTenancy()->where('order_id', $order->id)->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Failed->value, 'failure_reason' => 'cancelled']);

            $order->loadMissing(['items.product', 'branch.restaurant']);
            $localDate = $order->placed_at?->setTimezone($order->branch->restaurant->timezone)->toDateString() ?? '';

            foreach ($order->items as $item) {
                if ($item->product !== null) {
                    $this->stock->release($item->product, $order->branch, $item->quantity, $localDate);
                }
            }

            return $order;
        });

        // Money already taken goes back through the provider, after the cancellation is saved.
        $paid = Payment::withoutTenancy()->where('order_id', $order->id)->where('status', PaymentStatus::Paid)->get();

        foreach ($paid as $payment) {
            if ($payment->refundableMinor() > 0) {
                $this->refunds->handle($payment, null, 'order_cancelled: '.$reason, $actorType.($actorId !== null ? ':'.$actorId : ''));
            }
        }

        return $order->refresh();
    }
}
