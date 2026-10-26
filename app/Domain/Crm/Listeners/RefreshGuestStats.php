<?php

declare(strict_types=1);

namespace App\Domain\Crm\Listeners;

use App\Domain\Crm\GuestStats;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;

/** A pickup adds a visit; a cancellation (or a refund after pickup) can take one away. */
final class RefreshGuestStats
{
    public function __construct(private readonly GuestStats $stats) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if (! in_array($event->to, [OrderStatus::PickedUp, OrderStatus::Cancelled], true) || $order->customer_id === null) {
            return;
        }

        $this->stats->refreshRestaurant($order->restaurant_id, [$order->customer_id]);
    }
}
