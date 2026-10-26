<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Listeners;

use App\Domain\Loyalty\Stamps;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Events\PaymentRefunded;
use App\Domain\Payments\Models\Refund;

/**
 * Stamps follow the order: added when the guest picks it up, taken back
 * when it is cancelled or its payment is refunded in full.
 */
final class StampOrders
{
    public function __construct(private readonly Stamps $stamps) {}

    public function handle(OrderStatusChanged|PaymentRefunded $event): void
    {
        if ($event instanceof PaymentRefunded) {
            $refund = $event->refund;
            $payment = Payment::withoutTenancy()->find($refund->payment_id);

            if ($refund->status === Refund::SUCCEEDED && $payment !== null && $payment->refundableMinor() === 0) {
                $order = Order::withoutTenancy()->find($payment->order_id);

                if ($order !== null) {
                    $this->stamps->reverseOrder($order, 'refunded');
                }
            }

            return;
        }

        match ($event->to) {
            OrderStatus::PickedUp => $this->stamps->earnForOrder($event->order),
            OrderStatus::Cancelled => $this->stamps->reverseOrder($event->order, 'cancelled'),
            default => null,
        };
    }
}
