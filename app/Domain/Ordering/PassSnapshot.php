<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Branches\Enums\StationKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Station;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the Pass board shows for one branch, as plain arrays: the open
 * orders hung per station (an order with items at two stations hangs twice,
 * each ticket with only its own items), what to start next, what just went
 * out, and the day's averages. Read-only and a fixed number of queries.
 * "Late" is the same rule as BranchPulse; items without a station (or at a
 * station the branch doesn't have) belong to the kitchen.
 */
final class PassSnapshot
{
    /** How long a ready order stays on the spike. */
    public const DONE_MINUTES = 15;

    /** A scheduled order joins "start next" this long before it should be started. */
    public const NEXT_MINUTES = 15;

    public const DONE_LIMIT = 12;

    /**
     * @return array{
     *     stations: list<array{kind: string, name: string, on: int, late: int, oldest_seconds: ?int, avg_seconds: ?int, load: float, tickets: list<array<string, mixed>>}>,
     *     next: list<array{order_id: int, number: string, status: string, due_at: ?string, items_count: int, notes: ?string}>,
     *     done: list<array{order_id: int, number: string, ready_at: string}>,
     *     totals: array{on_pass: int, late: int, ready_today: int, orders_today: int, avg_ticket_seconds: ?int},
     * }
     */
    public function of(Branch $branch): array
    {
        $now = CarbonImmutable::now();
        $dayStart = $now->setTimezone($branch->restaurant->timezone)->startOfDay()->utc();

        $open = Order::withoutTenancy()
            ->with(['items', 'lane'])
            ->where('branch_id', $branch->id)
            ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value, OrderStatus::Scheduled->value])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $finished = Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->whereNotNull('received_at')
            ->whereIn('status', [OrderStatus::Ready->value, OrderStatus::PickedUp->value])
            ->where('ready_at', '>=', $dayStart)
            ->orderByDesc('ready_at')
            ->get(['id', 'number', 'received_at', 'ready_at']);

        $ordersToday = Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->where('placed_at', '>=', $dayStart)
            ->whereNotIn('status', [OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value])
            ->count();

        $stationRows = Station::withoutTenancy()->where('branch_id', $branch->id)->where('is_active', true)->orderBy('sort')->get();
        $names = $this->stationNames($stationRows);

        $onRail = $open->filter(fn (Order $o): bool => in_array($o->status, [OrderStatus::Received, OrderStatus::Preparing], true))->values();

        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];
        foreach ($names as $kind => $name) {
            $groups[$kind] = [];
        }

        foreach ($onRail as $order) {
            $byStation = $order->items->whereNull('done_at')->groupBy(fn (OrderItem $item): string => $this->groupOf($item->station, $names));

            foreach ($byStation as $kind => $items) {
                $groups[$kind] ??= [];
                $groups[$kind][] = $this->ticket($order, $items, $now);
            }
        }

        $averages = $this->stationAverages($branch, $dayStart, $names);
        $capacity = max(1, (int) $branch->capacity_per_slot);

        $stations = [];
        foreach ($groups as $kind => $tickets) {
            $kind = (string) $kind;
            $ages = array_map(fn (array $t): int => (int) $t['age_seconds'], $tickets);
            $stations[] = [
                'kind' => $kind,
                'name' => $names[$kind] ?? (string) __('ops.pass.kitchen'),
                'on' => count($tickets),
                'late' => count(array_filter($tickets, fn (array $t): bool => (bool) $t['late'])),
                'oldest_seconds' => $ages === [] ? null : max($ages),
                'avg_seconds' => $averages[$kind] ?? null,
                'load' => round(count($tickets) / $capacity, 2),
                'tickets' => $tickets,
            ];
        }

        $soon = $now->addMinutes(self::NEXT_MINUTES);
        $next = $open->where('status', OrderStatus::Received)->values()
            ->concat($open->where('status', OrderStatus::Scheduled)
                ->filter(fn (Order $o): bool => $o->prepare_at === null || $o->prepare_at->lessThanOrEqualTo($soon))
                ->sortBy(fn (Order $o): int => $o->prepare_at?->getTimestamp() ?? 0)
                ->values())
            ->map(fn (Order $o): array => [
                'order_id' => $o->id,
                'number' => $o->displayNumber(),
                'status' => $o->status->value,
                'due_at' => $o->prepare_at?->toIso8601String(),
                'items_count' => (int) $o->items->sum('quantity'),
                'notes' => filled($o->notes) ? (string) $o->notes : null,
            ])->values()->all();
        $next = array_values($next);

        $durations = $finished->map(fn (Order $o): int => (int) $o->received_at?->diffInSeconds($o->ready_at, true));

        return [
            'stations' => $stations,
            'next' => $next,
            'done' => array_values($finished->filter(fn (Order $o): bool => $o->ready_at !== null && $o->ready_at->greaterThanOrEqualTo($now->subMinutes(self::DONE_MINUTES)))
                ->take(self::DONE_LIMIT)
                ->map(fn (Order $o): array => ['order_id' => $o->id, 'number' => $o->displayNumber(), 'ready_at' => (string) $o->ready_at?->toIso8601String()])
                ->values()->all()),
            'totals' => [
                'on_pass' => $onRail->count(),
                'late' => $onRail->filter(fn (Order $o): bool => $this->isLate($o, $now))->count(),
                'ready_today' => $finished->count(),
                'orders_today' => $ordersToday,
                'avg_ticket_seconds' => $durations->isEmpty() ? null : (int) round((float) $durations->avg()),
            ],
        ];
    }

    /** Same rule as BranchPulse: received or preparing, and past received_at + prep minutes. */
    public function isLate(Order $order, CarbonImmutable $now): bool
    {
        return in_array($order->status, [OrderStatus::Received, OrderStatus::Preparing], true)
            && $order->received_at !== null
            && $order->received_at->addMinutes($order->prep_minutes)->lessThan($now);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return array<string, mixed>
     */
    private function ticket(Order $order, Collection $items, CarbonImmutable $now): array
    {
        $locale = app()->getLocale();
        $received = $order->received_at ?? $now;
        $vehicle = array_filter((array) $order->vehicle, fn ($value): bool => filled($value));

        return [
            'order_id' => $order->id,
            'number' => $order->displayNumber(),
            'status' => $order->status->value,
            'received_at' => $received->toIso8601String(),
            'target_at' => $received->addMinutes($order->prep_minutes)->toIso8601String(),
            'age_seconds' => (int) $received->diffInSeconds($now, true),
            'late' => $this->isLate($order, $now),
            'car' => $vehicle === [] ? null : implode(' · ', array_filter([trim(($vehicle['color'] ?? '').' '.($vehicle['make'] ?? '').' '.($vehicle['model'] ?? '')), $vehicle['plate'] ?? null])),
            'lane' => $order->lane?->name,
            'arrived' => $order->arrived_at !== null,
            'payment' => $order->payment_status->getLabel(),
            'notes' => filled($order->notes) ? (string) $order->notes : null,
            'items' => $items->values()->map(fn (OrderItem $item): array => [
                'quantity' => $item->quantity,
                'name' => $item->localName($locale),
                'options' => array_values(array_filter($item->optionLabels($locale))),
                'notes' => filled($item->notes) ? (string) $item->notes : null,
            ])->all(),
        ];
    }

    /**
     * Station kind => name, in the branch's order; the kitchen (general) always has a name.
     *
     * @param  Collection<int, Station>  $stations
     * @return array<string, string>
     */
    private function stationNames(Collection $stations): array
    {
        $names = [];
        foreach ($stations as $station) {
            $names[$station->kind->value] ??= (string) $station->name;
        }

        return $names;
    }

    /** @param array<string, string> $names */
    private function groupOf(?StationKind $station, array $names): string
    {
        $kind = ($station ?? StationKind::General)->value;

        return array_key_exists($kind, $names) ? $kind : StationKind::General->value;
    }

    /**
     * Mean time from the order being received to the station finishing its items, today.
     * An item is finished when its station marked it done, or else when the whole order became ready.
     *
     * @param  array<string, string>  $names
     * @return array<string, int>
     */
    private function stationAverages(Branch $branch, CarbonImmutable $dayStart, array $names): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.branch_id', $branch->id)
            ->whereNotNull('orders.received_at')
            ->where(fn ($query) => $query->where('order_items.done_at', '>=', $dayStart)
                ->orWhere(fn ($query) => $query->whereNull('order_items.done_at')->where('orders.ready_at', '>=', $dayStart)))
            ->get(['order_items.station', 'order_items.done_at', 'orders.ready_at', 'orders.received_at']);

        return $rows->groupBy(fn (object $row): string => $this->groupOf(StationKind::tryFrom((string) $row->station), $names))
            ->map(fn (Collection $group): int => (int) round((float) $group->avg(fn (object $row): int => (int) CarbonImmutable::parse($row->received_at)->diffInSeconds(CarbonImmutable::parse($row->done_at ?? $row->ready_at), true))))
            ->all();
    }
}
