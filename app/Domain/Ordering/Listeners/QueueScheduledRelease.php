<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Listeners;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Jobs\ReleaseScheduledOrder;

/** A held order with a start time gets a job for that moment. */
final class QueueScheduledRelease
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if ($event->to !== OrderStatus::Scheduled || $order->prepare_at === null) {
            return;
        }

        ReleaseScheduledOrder::dispatch($order->id)->delay($order->prepare_at);
    }
}
