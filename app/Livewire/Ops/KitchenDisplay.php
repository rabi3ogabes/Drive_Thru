<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Branches\Enums\StationKind;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Livewire\Ops\Concerns\OpsScreen;
use App\Support\Lang;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The kitchen display: new, cooking and ready columns with timers against
 * each order's target, urgency in colour + icon + words, one-tap advance,
 * cancel with a reason and re-enable. A station screen shows only its own
 * items and marks its part done; the order is ready when every station is.
 */
class KitchenDisplay extends Component
{
    use OpsScreen;

    /** A station screen (grill, drinks…) or null for the whole kitchen. */
    #[Locked]
    public ?string $station = null;

    public function mount(int $branchId, ?int $deviceId = null, ?string $station = null): void
    {
        $this->branchId = $branchId;
        $this->deviceId = $deviceId;
        $operator = $this->operator();
        $this->station = $operator->station !== null ? $operator->station->value : StationKind::tryFrom((string) $station)?->value;
        $this->seen = $this->openIds();
    }

    public function advance(int $orderId): void
    {
        $order = $this->order($orderId);
        $label = $order->status === OrderStatus::Received ? Lang::text('ops.kitchen.started', ['number' => $order->displayNumber()]) : Lang::text('ops.kitchen.readied', ['number' => $order->displayNumber()]);

        $this->attempt(fn () => $this->desk()->advance($this->operator(), $order), $label);
    }

    public function stationDone(int $orderId): void
    {
        $station = $this->stationKind();

        if ($station === null) {
            $this->advance($orderId);

            return;
        }

        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->stationDone($this->operator(), $order, $station), Lang::text('ops.kitchen.station_done', ['number' => $order->displayNumber()]));
    }

    public function cancel(int $orderId, string $reason): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->cancel($this->operator(), $order, $reason), Lang::text('ops.kitchen.cancelled', ['number' => $order->displayNumber()]));
    }

    public function reEnable(int $orderId, string $reason): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->reEnable($this->operator(), $order, $reason), Lang::text('ops.kitchen.re_enabled', ['number' => $order->displayNumber()]));
    }

    public function render(): View
    {
        $branch = $this->branch();
        $tz = $branch->restaurant->timezone;
        $station = $this->stationKind();
        $dayStart = CarbonImmutable::now($tz)->startOfDay()->utc();

        $open = Order::withoutTenancy()
            ->with(['items', 'lane'])
            ->where('branch_id', $this->branchId)
            ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value, OrderStatus::Ready->value, OrderStatus::Scheduled->value])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        if ($station !== null) {
            $open = $open->filter(fn (Order $order): bool => $order->status === OrderStatus::Ready || $order->items->contains(fn (OrderItem $item): bool => $this->madeAt($item, $station) && $item->done_at === null))->values();
        }

        $new = $this->newlyArrived(array_values(array_map('intval', $open->where('status', OrderStatus::Received)->modelKeys())));

        $done = Order::withoutTenancy()
            ->where('branch_id', $this->branchId)
            ->where('ready_at', '>=', $dayStart)
            ->whereNotNull('received_at')
            ->get(['received_at', 'ready_at']);

        $cancelled = Order::withoutTenancy()
            ->with('items')
            ->where('branch_id', $this->branchId)
            ->where('status', OrderStatus::Cancelled->value)
            ->where('cancelled_at', '>=', now()->subMinutes(30))
            ->latest('cancelled_at')
            ->limit(10)
            ->get();

        if ($new !== []) {
            $this->dispatch('ops-new-orders', count: count($new));
        }

        return view('livewire.ops.kitchen-display', [
            'branch' => $branch,
            'tz' => $tz,
            'station' => $station,
            'columns' => [
                'received' => $open->where('status', OrderStatus::Received)->values(),
                'preparing' => $open->where('status', OrderStatus::Preparing)->values(),
                'ready' => $open->where('status', OrderStatus::Ready)->sortByDesc('ready_at')->take(12)->values(),
            ],
            'held' => $this->held($open),
            'cancelled' => $cancelled,
            'fresh' => $new,
            'doneToday' => $done->count(),
            'averagePrep' => $done->isEmpty() ? null : (int) round((float) ($done->avg(fn (Order $o): float => (float) $o->received_at?->diffInSeconds($o->ready_at) / 60) ?? 0)),
            'load' => $this->load()->of($branch),
        ]);
    }

    /** Whether an item is made at this station (items without one go to the general station). */
    public function madeAt(OrderItem $item, StationKind $station): bool
    {
        return ($item->station ?? StationKind::General) === $station;
    }

    private function stationKind(): ?StationKind
    {
        return $this->station === null ? null : StationKind::tryFrom($this->station);
    }

    /**
     * Held orders starting within the hour, and "on my way" orders waiting for the guest.
     *
     * @param  Collection<int, Order>  $open
     * @return Collection<int, Order>
     */
    private function held(Collection $open): Collection
    {
        return $open->where('status', OrderStatus::Scheduled)
            ->filter(fn (Order $o): bool => $o->prepare_at === null || $o->prepare_at->lessThanOrEqualTo(now()->addHour()))
            ->sortBy(fn (Order $o): int => $o->prepare_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @return list<int>
     */
    private function openIds(): array
    {
        return array_values(array_map('intval', Order::withoutTenancy()
            ->where('branch_id', $this->branchId)
            ->where('status', OrderStatus::Received->value)
            ->pluck('id')
            ->all()));
    }
}
