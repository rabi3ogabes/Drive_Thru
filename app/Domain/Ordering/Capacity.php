<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Orders per 15-minute kitchen slot. When a branch sets a capacity and the
 * slot an order would start in is full, the next slot with room is offered.
 */
final class Capacity
{
    public const SLOT_MINUTES = 15;

    public function __construct(private readonly BranchSchedule $schedule) {}

    public function slotStart(CarbonInterface $at): CarbonImmutable
    {
        $at = CarbonImmutable::instance($at)->utc()->startOfMinute();

        return $at->subMinutes($at->minute % self::SLOT_MINUTES);
    }

    public function hasRoom(Branch $branch, CarbonInterface $prepareAt): bool
    {
        if ($branch->capacity_per_slot === null) {
            return true;
        }

        $start = $this->slotStart($prepareAt);

        $taken = Order::withoutTenancy()
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::PendingPayment])
            ->whereBetween('prepare_at', [$start, $start->addMinutes(self::SLOT_MINUTES)->subSecond()])
            ->count();

        return $taken < $branch->capacity_per_slot;
    }

    /**
     * The first slot from $from with room while the branch is open, within
     * the next 24 hours, or null when there is none.
     */
    public function nextAvailable(Branch $branch, CarbonInterface $from): ?CarbonImmutable
    {
        $at = $this->slotStart($from)->addMinutes(self::SLOT_MINUTES);
        $tz = $branch->restaurant->timezone;

        for ($i = 0; $i < (24 * 60 / self::SLOT_MINUTES); $i++, $at = $at->addMinutes(self::SLOT_MINUTES)) {
            if ($this->schedule->withinHours($branch, $at->setTimezone($tz)) && $this->hasRoom($branch, $at)) {
                return $at;
            }
        }

        return null;
    }
}
