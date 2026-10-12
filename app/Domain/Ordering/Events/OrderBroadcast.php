<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Events;

use App\Domain\Ordering\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * What the live screens and the guest receive when an order changes: ids,
 * status and times only, never names or phone numbers. Screens use it as a
 * signal to refresh from the server, so a missed message is never a problem.
 */
final class OrderBroadcast implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $branchId,
        public readonly int $restaurantId,
        public readonly string $shortCode,
        public readonly string $status,
        public readonly ?string $from,
        public readonly string $what,
    ) {}

    public static function for(Order $order, string $what, ?string $from = null): self
    {
        return new self($order->id, $order->branch_id, $order->restaurant_id, $order->short_code, $order->status->value, $from, $what);
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->branchId}.orders"),
            new PrivateChannel("restaurant.{$this->restaurantId}.dashboard"),
            new PrivateChannel("order.{$this->shortCode}"),
            new PrivateChannel('platform.dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderId,
            'branch_id' => $this->branchId,
            'status' => $this->status,
            'from' => $this->from,
            'what' => $this->what,
            'at' => now()->toIso8601String(),
        ];
    }
}
