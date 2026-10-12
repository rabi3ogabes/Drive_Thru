<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * A branch's live numbers for the cashier board, the TV and the owner's
 * multi-branch screen. Days are the branch's local days; comparisons are
 * "so far today" against the same time yesterday and on the same weekday
 * last week, so a morning never looks like a bad day.
 */
final class BranchPulse
{
    public const HEALTHY = 'healthy';

    public const BUSY = 'busy';

    public const ATTENTION = 'attention';

    /** A guest at the window longer than this is an alert. */
    public const ARRIVED_WAIT_MINUTES = 5;

    public function __construct(
        private readonly KitchenLoad $load,
        private readonly BranchSchedule $schedule,
    ) {}

    /**
     * @return array{
     *     new: int, preparing: int, ready: int, held: int, arrived_waiting: int, delayed: int,
     *     orders_today: int, sales_today: int, sales_yesterday: int, sales_last_week: int,
     *     average_prep: ?int, estimate: int, level: string, health: string, open: bool,
     *     hourly: array<int, array{today: int, yesterday: int}>, alerts: list<array{tone: string, key: string, number?: string, minutes?: int}>
     * }
     */
    public function of(Branch $branch): array
    {
        $tz = $branch->restaurant->timezone;
        $now = CarbonImmutable::now($tz);
        $today = $now->startOfDay();

        $open = Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value, OrderStatus::Ready->value, OrderStatus::Scheduled->value])
            ->get(['id', 'number', 'status', 'received_at', 'prep_minutes', 'arrived_at']);

        $delayed = $open->filter(fn (Order $o): bool => in_array($o->status, [OrderStatus::Received, OrderStatus::Preparing], true)
            && $o->received_at !== null && $o->received_at->addMinutes($o->prep_minutes)->isPast());
        $waiting = $open->filter(fn (Order $o): bool => $o->arrived_at !== null && $o->arrived_at->addMinutes(self::ARRIVED_WAIT_MINUTES)->isPast());

        $sold = fn (CarbonImmutable $from, CarbonImmutable $to): int => (int) $this->placed($branch, $from, $to)->sum('total_minor');

        $todays = $this->placed($branch, $today, $now)->get(['total_minor', 'placed_at']);
        $yesterdays = $this->placed($branch, $today->subDay(), $today)->get(['total_minor', 'placed_at']);

        $byHour = function ($rows) use ($tz): array {
            $sums = array_fill(0, 24, 0);
            foreach ($rows as $row) {
                if ($row->placed_at !== null) {
                    $sums[(int) $row->placed_at->setTimezone($tz)->format('G')] += $row->total_minor;
                }
            }

            return $sums;
        };
        $todayByHour = $byHour($todays);
        $yesterdayByHour = $byHour($yesterdays);
        $hourly = [];
        foreach (range(0, 23) as $hour) {
            $hourly[$hour] = ['today' => $todayByHour[$hour], 'yesterday' => $yesterdayByHour[$hour]];
        }

        $done = Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->whereNotNull('received_at')
            ->where('ready_at', '>=', $today->utc())
            ->get(['received_at', 'ready_at']);

        $load = $this->load->of($branch);
        $isOpen = $this->schedule->state($branch)->acceptsOrders();

        $alerts = [];
        foreach ($delayed->take(5) as $order) {
            $alerts[] = ['tone' => 'critical', 'key' => 'delayed', 'number' => $order->displayNumber(), 'minutes' => (int) $order->received_at?->diffInMinutes(now())];
        }
        foreach ($waiting->take(5) as $order) {
            $alerts[] = ['tone' => 'warning', 'key' => 'waiting', 'number' => $order->displayNumber(), 'minutes' => (int) $order->arrived_at?->diffInMinutes(now())];
        }
        if ($load['level'] === KitchenLoad::BUSY) {
            $alerts[] = ['tone' => 'warning', 'key' => 'overloaded', 'minutes' => $load['estimate']];
        }
        $failed = Order::withoutTenancy()->where('branch_id', $branch->id)->where('payment_status', 'failed')->where('status', OrderStatus::PendingPayment->value)->where('placed_at', '>=', $now->subHour()->utc())->count();
        if ($failed > 0) {
            $alerts[] = ['tone' => 'info', 'key' => 'payment_failed', 'minutes' => $failed];
        }

        $health = match (true) {
            $delayed->count() >= 3 || $waiting->isNotEmpty() => self::ATTENTION,
            $delayed->isNotEmpty() || $load['level'] === KitchenLoad::BUSY => self::BUSY,
            default => self::HEALTHY,
        };

        return [
            'new' => $open->where('status', OrderStatus::Received)->count(),
            'preparing' => $open->where('status', OrderStatus::Preparing)->count(),
            'ready' => $open->where('status', OrderStatus::Ready)->count(),
            'held' => $open->where('status', OrderStatus::Scheduled)->count(),
            'arrived_waiting' => $open->whereNotNull('arrived_at')->count(),
            'delayed' => $delayed->count(),
            'orders_today' => $todays->count(),
            'sales_today' => (int) $todays->sum('total_minor'),
            'sales_yesterday' => $sold($today->subDay(), $now->subDay()),
            'sales_last_week' => $sold($today->subWeek(), $now->subWeek()),
            'average_prep' => $done->isEmpty() ? null : (int) round((float) ($done->avg(fn (Order $o): float => (float) $o->received_at?->diffInSeconds($o->ready_at) / 60) ?? 0)),
            'estimate' => $load['estimate'],
            'level' => $load['level'],
            'health' => $health,
            'open' => $isOpen,
            'hourly' => $hourly,
            'alerts' => $alerts,
        ];
    }

    /**
     * Orders placed in a window that count as sales (not cancelled, not unpaid online).
     *
     * @return Builder<Order>
     */
    private function placed(Branch $branch, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::PendingPayment->value])
            ->whereBetween('placed_at', [$from->utc(), $to->utc()]);
    }
}
