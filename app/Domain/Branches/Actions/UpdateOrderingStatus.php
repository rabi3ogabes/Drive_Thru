<?php

declare(strict_types=1);

namespace App\Domain\Branches\Actions;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The branch's live controls: pause new orders, add busy-mode minutes, or
 * resume normal service. Every change is written to the audit log.
 */
final class UpdateOrderingStatus
{
    public function __construct(private readonly BranchSchedule $schedule) {}

    /**
     * Stop new orders for some minutes, or until the current shift ends when
     * $minutes is null (end of day if the branch is open around the clock).
     */
    public function pause(Branch $branch, ?int $minutes, ?User $actor = null): Branch
    {
        $until = $minutes !== null
            ? CarbonImmutable::now()->addMinutes(max(1, $minutes))
            : ($this->schedule->closesAt($branch)?->utc()
                ?? CarbonImmutable::now()->setTimezone($branch->restaurant->timezone ?? 'UTC')->endOfDay()->utc());

        $branch->forceFill(['paused_until' => $until])->save();

        $this->log($branch, $actor, 'branch.paused', ['until' => $until->toIso8601String(), 'minutes' => $minutes]);

        return $branch;
    }

    /** Add extra minutes to every quoted prep time for a while. */
    public function busy(Branch $branch, int $extraMinutes, int $forMinutes, ?User $actor = null): Branch
    {
        $until = CarbonImmutable::now()->addMinutes(max(1, $forMinutes));

        $branch->forceFill(['busy_extra_minutes' => max(1, $extraMinutes), 'busy_until' => $until])->save();

        $this->log($branch, $actor, 'branch.busy', ['extra_minutes' => $extraMinutes, 'until' => $until->toIso8601String()]);

        return $branch;
    }

    /** Back to normal: clears both pause and busy mode. */
    public function resume(Branch $branch, ?User $actor = null): Branch
    {
        $branch->forceFill(['paused_until' => null, 'busy_extra_minutes' => 0, 'busy_until' => null])->save();

        $this->log($branch, $actor, 'branch.resumed');

        return $branch;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Branch $branch, ?User $actor, string $event, array $properties = []): void
    {
        activity()->performedOn($branch)->causedBy($actor)->event($event)->withProperties($properties)->log($event);
    }
}
