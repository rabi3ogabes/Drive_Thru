<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Carbon\CarbonImmutable;

/**
 * How busy a branch's kitchen is right now, from real data: the average time
 * recent orders took from received to ready (last 3 hours, once there are at
 * least 3), the orders waiting or cooking, and busy mode. Guests see the
 * estimate and the busy level; screens see the same numbers.
 */
final class KitchenLoad
{
    public const QUIET = 'quiet';

    public const MODERATE = 'moderate';

    public const BUSY = 'busy';

    private const WINDOW_HOURS = 3;

    private const MIN_SAMPLES = 3;

    /** @var array<int, array{queue: int, average: ?int, estimate: int, level: string}> */
    private array $memo = [];

    /**
     * @return array{queue: int, average: ?int, estimate: int, level: string}
     */
    public function of(Branch $branch): array
    {
        return $this->memo[$branch->id] ??= $this->measure($branch);
    }

    /** Minutes a new order should take, starting now. */
    public function estimate(Branch $branch): int
    {
        return $this->of($branch)['estimate'];
    }

    public function level(Branch $branch): string
    {
        return $this->of($branch)['level'];
    }

    /** Forget measurements (after orders change within one request or job). */
    public function flush(): void
    {
        $this->memo = [];
    }

    /**
     * @return array{queue: int, average: ?int, estimate: int, level: string}
     */
    private function measure(Branch $branch): array
    {
        $now = CarbonImmutable::now();

        $queue = Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value])
            ->count();

        $recent = Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->whereNotNull('received_at')
            ->where('ready_at', '>=', $now->subHours(self::WINDOW_HOURS))
            ->latest('ready_at')
            ->limit(30)
            ->get(['received_at', 'ready_at']);

        $durations = $recent
            ->map(fn (Order $o): float => max(1.0, (float) $o->received_at?->diffInSeconds($o->ready_at) / 60))
            ->filter(fn (float $minutes): bool => $minutes <= 120)
            ->values();

        $average = $durations->count() >= self::MIN_SAMPLES ? (int) round($durations->avg() ?? 0) : null;
        $base = $average ?? $branch->avg_prep_minutes;

        // Each order beyond the two the kitchen handles at once adds a minute (capped).
        $estimate = $base + min(30, max(0, $queue - 2)) + ($branch->isBusy($now) ? $branch->busy_extra_minutes : 0);

        $level = match (true) {
            $branch->isBusy($now) || $queue >= 8 || $estimate >= $branch->avg_prep_minutes * 2 => self::BUSY,
            $queue >= 3 || $estimate >= (int) ceil($branch->avg_prep_minutes * 1.4) => self::MODERATE,
            default => self::QUIET,
        };

        return ['queue' => $queue, 'average' => $average, 'estimate' => max(1, $estimate), 'level' => $level];
    }
}
