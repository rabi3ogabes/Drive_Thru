<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Models\Order;

/**
 * Held orders whose start time has come go to the kitchen. Runs every
 * minute from the scheduler and from a job queued for each order's start
 * time; both are safe to repeat.
 */
final class ReleaseScheduledOrders
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /** @return int how many orders were released */
    public function handle(?int $orderId = null): int
    {
        $due = Order::withoutTenancy()
            ->where('status', OrderStatus::Scheduled->value)
            ->whereNotNull('prepare_at')
            ->where('prepare_at', '<=', now())
            ->when($orderId !== null, fn ($query) => $query->whereKey($orderId))
            ->orderBy('prepare_at')
            ->limit(200)
            ->get();

        $released = 0;

        foreach ($due as $order) {
            try {
                $this->transition->handle($order, OrderStatus::Received, TransitionOrder::SYSTEM, note: 'scheduled.release');
                $released++;
            } catch (InvalidTransition) {
                // Already released or cancelled on another path.
            }
        }

        return $released;
    }
}
