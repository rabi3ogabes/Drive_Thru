<?php

declare(strict_types=1);

namespace App\Domain\Branches;

use App\Domain\Branches\Enums\BranchState;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\BranchClosure;
use App\Domain\Branches\Models\BranchHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Answers "is this branch taking orders right now, and if not, when?" in the
 * restaurant's timezone.
 *
 * Rules, in order: switched off → paused → holiday/closure → weekly hours.
 * A branch with no hours at all is open around the clock. Shifts may be split
 * (several per day) and may run past midnight (closes_at ≤ opens_at).
 */
final class BranchSchedule
{
    public function state(Branch $branch, ?CarbonInterface $at = null): BranchState
    {
        $at = CarbonImmutable::instance($at ?? now());

        if (! $branch->is_open) {
            return BranchState::Disabled;
        }

        if ($branch->isPaused($at)) {
            return BranchState::Paused;
        }

        $local = $at->setTimezone($this->timezone($branch));

        if ($this->closureOn($branch, $local->toDateString()) !== null) {
            return BranchState::Holiday;
        }

        if (! $this->withinHours($branch, $local)) {
            return BranchState::Closed;
        }

        return $branch->isBusy($at) ? BranchState::Busy : BranchState::Open;
    }

    public function withinHours(Branch $branch, CarbonImmutable $local): bool
    {
        $hours = $this->hours($branch);

        if ($hours->isEmpty()) {
            return true;
        }

        return $this->currentShiftEnd($hours, $local) !== null;
    }

    /**
     * When the current shift ends (local time), or null if closed now / open 24 h.
     */
    public function closesAt(Branch $branch, ?CarbonInterface $at = null): ?CarbonImmutable
    {
        $hours = $this->hours($branch);

        if ($hours->isEmpty()) {
            return null;
        }

        $local = CarbonImmutable::instance($at ?? now())->setTimezone($this->timezone($branch));

        return $this->currentShiftEnd($hours, $local);
    }

    /**
     * The next moment the branch opens (local time), skipping closures.
     * Null when it is open now, has no hours, or nothing opens within a week.
     */
    public function nextOpening(Branch $branch, ?CarbonInterface $at = null): ?CarbonImmutable
    {
        $hours = $this->hours($branch);

        if ($hours->isEmpty()) {
            return null;
        }

        $local = CarbonImmutable::instance($at ?? now())->setTimezone($this->timezone($branch));

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $local->startOfDay()->addDays($offset);

            if ($this->closureOn($branch, $day->toDateString()) !== null) {
                continue;
            }

            $starts = $hours->where('weekday', $day->dayOfWeek)
                ->map(fn (BranchHour $hour): CarbonImmutable => $this->at($day, $hour->opens_at))
                ->filter(fn (CarbonImmutable $start): bool => $start->greaterThan($local))
                ->sort()
                ->values();

            if ($starts->isNotEmpty()) {
                return $starts->first();
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, BranchHour>  $hours
     */
    private function currentShiftEnd(Collection $hours, CarbonImmutable $local): ?CarbonImmutable
    {
        $time = $local->format('H:i');
        $today = $local->startOfDay();

        foreach ($hours->where('weekday', $local->dayOfWeek) as $hour) {
            if ($hour->crossesMidnight()) {
                if ($time >= $hour->opens_at) {
                    return $this->at($today->addDay(), $hour->closes_at);
                }
            } elseif ($time >= $hour->opens_at && $time < $hour->closes_at) {
                return $this->at($today, $hour->closes_at);
            }
        }

        // Yesterday's late shift still running after midnight.
        $yesterday = $local->subDay()->dayOfWeek;

        foreach ($hours->where('weekday', $yesterday) as $hour) {
            if ($hour->crossesMidnight() && $time < $hour->closes_at) {
                return $this->at($today, $hour->closes_at);
            }
        }

        return null;
    }

    /**
     * When a branch that isn't taking orders will take them again (local
     * time): the end of a pause if it falls within opening hours, otherwise
     * the next opening. Null when switched off or nothing opens within a week.
     */
    public function reopensAt(Branch $branch, ?CarbonInterface $at = null): ?CarbonImmutable
    {
        $at = CarbonImmutable::instance($at ?? now());

        if (! $branch->is_open) {
            return null;
        }

        $from = $branch->isPaused($at) && $branch->paused_until !== null ? $branch->paused_until : $at;
        $local = $from->setTimezone($this->timezone($branch));

        if ($from->greaterThan($at)
            && $this->closureOn($branch, $local->toDateString()) === null
            && $this->withinHours($branch, $local)) {
            return $local;
        }

        return $this->nextOpening($branch, $from);
    }

    /**
     * The week for display, Sunday first: weekday => ["07:00–11:00", …].
     * An empty list means closed that day; null means open around the clock.
     *
     * @return ?array<int, list<string>>
     */
    public function weekly(Branch $branch): ?array
    {
        $hours = $this->hours($branch);

        if ($hours->isEmpty()) {
            return null;
        }

        $week = [];

        foreach (range(0, 6) as $weekday) {
            $week[$weekday] = array_values($hours->where('weekday', $weekday)
                ->sortBy('opens_at')
                ->map(fn (BranchHour $hour): string => $hour->opens_at.'–'.$hour->closes_at)
                ->all());
        }

        return $week;
    }

    /**
     * Upcoming closures (today onwards) for guests to plan around.
     *
     * @return Collection<int, BranchClosure>
     */
    public function upcomingClosures(Branch $branch, int $limit = 3): Collection
    {
        $today = CarbonImmutable::now($this->timezone($branch))->toDateString();
        $branch->loadMissing('closures');

        return $branch->closures
            ->filter(fn (BranchClosure $closure): bool => $closure->ends_on->toDateString() >= $today)
            ->take($limit)
            ->values();
    }

    public function closureOn(Branch $branch, string $date): ?BranchClosure
    {
        $branch->loadMissing('closures');

        return $branch->closures->first(fn (BranchClosure $closure): bool => $closure->covers($date));
    }

    /**
     * @return Collection<int, BranchHour>
     */
    private function hours(Branch $branch): Collection
    {
        $branch->loadMissing('hours');

        return $branch->hours;
    }

    private function timezone(Branch $branch): string
    {
        $branch->loadMissing('restaurant');

        return $branch->restaurant->timezone ?? config('app.timezone');
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }
}
