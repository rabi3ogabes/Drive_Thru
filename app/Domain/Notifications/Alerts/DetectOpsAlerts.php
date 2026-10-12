<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Alerts;

use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\KitchenLoad;
use App\Domain\Ordering\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Runs every minute: finds orders running late in the kitchen, guests left
 * waiting at the window and kitchens drowning in orders, and alerts the
 * branch: once per order, and at most every half hour for a kitchen.
 * The "alerted" timestamps are claimed with a compare-and-set update, so two
 * overlapping runs never alert twice.
 */
final class DetectOpsAlerts
{
    /** Minutes past the promised prep time before the kitchen is told. */
    public const DELAY_GRACE_MINUTES = 5;

    /** Minutes a guest waits at the window before the team is told. */
    public const WAIT_MINUTES = 5;

    /** Orders waiting or cooking before a kitchen not already in busy mode is flagged. */
    public const OVERLOAD_QUEUE = 8;

    public const OVERLOAD_REPEAT_MINUTES = 30;

    public function __construct(
        private readonly StaffAlerts $alerts,
        private readonly KitchenLoad $load,
    ) {}

    /**
     * @return array{delayed: int, waiting: int, overloaded: int}
     */
    public function handle(): array
    {
        $delayed = 0;
        $waiting = 0;

        $this->lateOrders()->each(function (Order $order) use (&$delayed): void {
            if ($this->claim($order, 'delay_alerted_at')) {
                $minutes = (int) $order->received_at?->addMinutes($order->prep_minutes)->diffInMinutes(now(), true);
                $this->alerts->send(AlertKind::OrderDelayed, $order->restaurant_id, $order->branch, [
                    'number' => $order->number,
                    'minutes' => max(1, $minutes),
                    'branch' => $order->branch->getTranslations('name'),
                ], route('ops.screen', [$order->restaurant, $order->branch->code, 'live']));
                $delayed++;
            }
        });

        $this->waitingGuests()->each(function (Order $order) use (&$waiting): void {
            if ($this->claim($order, 'wait_alerted_at')) {
                $this->alerts->send(AlertKind::GuestWaiting, $order->restaurant_id, $order->branch, [
                    'number' => $order->number,
                    'minutes' => max(1, (int) $order->arrived_at?->diffInMinutes(now(), true)),
                    'branch' => $order->branch->getTranslations('name'),
                ], route('ops.screen', [$order->restaurant, $order->branch->code, 'pickup']));
                $waiting++;
            }
        });

        $overloaded = 0;
        $this->load->flush(); // measurements are memoised; a long-running scheduler must see the kitchen now

        foreach ($this->busyBranches() as $branch) {
            $queue = $this->load->of($branch)['queue'];

            if ($queue >= self::OVERLOAD_QUEUE && ! $branch->isBusy(now()->toImmutable())
                && Cache::add('ops-alert:overloaded:'.$branch->id, true, now()->addMinutes(self::OVERLOAD_REPEAT_MINUTES))) {
                $this->alerts->send(AlertKind::KitchenOverloaded, $branch->restaurant_id, $branch, [
                    'count' => $queue,
                    'branch' => $branch->getTranslations('name'),
                ], route('ops.screen', [$branch->restaurant, $branch->code, 'kitchen']));
                $overloaded++;
            }
        }

        return ['delayed' => $delayed, 'waiting' => $waiting, 'overloaded' => $overloaded];
    }

    /**
     * @return Collection<int, Order>
     */
    private function lateOrders(): Collection
    {
        return Order::withoutTenancy()
            ->with(['branch', 'restaurant'])
            ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value])
            ->whereNull('delay_alerted_at')
            ->whereNotNull('received_at')
            ->where('received_at', '>=', now()->subDay())
            ->get()
            ->filter(fn (Order $order): bool => $order->received_at !== null
                && $order->received_at->addMinutes($order->prep_minutes + self::DELAY_GRACE_MINUTES)->isPast())
            ->values();
    }

    /**
     * @return Collection<int, Order>
     */
    private function waitingGuests(): Collection
    {
        return Order::withoutTenancy()
            ->with(['branch', 'restaurant'])
            ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value, OrderStatus::Ready->value])
            ->whereNull('wait_alerted_at')
            ->whereNotNull('arrived_at')
            ->where('arrived_at', '<=', now()->subMinutes(self::WAIT_MINUTES))
            ->where('arrived_at', '>=', now()->subDay())
            ->get();
    }

    /**
     * Branches with at least the overload threshold of open kitchen orders.
     *
     * @return Collection<int, Branch>
     */
    private function busyBranches(): Collection
    {
        $ids = Order::withoutTenancy()
            ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value])
            ->groupBy('branch_id')
            // A literal, not a binding: SQLite compares a bound "8" as text and never matches.
            ->havingRaw('count(*) >= '.self::OVERLOAD_QUEUE)
            ->pluck('branch_id');

        return $ids->isEmpty() ? collect() : Branch::withoutTenancy()->with('restaurant')->whereKey($ids)->get();
    }

    private function claim(Order $order, string $column): bool
    {
        return Order::withoutTenancy()->whereKey($order->id)->whereNull($column)->update([$column => now()]) === 1;
    }
}
