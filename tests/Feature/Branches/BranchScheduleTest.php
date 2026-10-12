<?php

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Enums\BranchState;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\BranchClosure;
use App\Domain\Branches\Models\BranchHour;
use Carbon\CarbonImmutable;

/** A Doha (UTC+3) wall-clock moment. 2026-10-11 is a Sunday (weekday 0). */
function doha(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'Asia/Qatar');
}

/**
 * @param  list<array{0: int, 1: string, 2: string}>  $shifts
 */
function branchWithHours(array $shifts = [], array $attributes = []): Branch
{
    $branch = Branch::factory()->create(array_merge(['restaurant_id' => restaurant()->id], $attributes));

    foreach ($shifts as [$weekday, $opens, $closes]) {
        BranchHour::withoutTenancy()->create([
            'restaurant_id' => $branch->restaurant_id,
            'branch_id' => $branch->id,
            'weekday' => $weekday,
            'opens_at' => $opens,
            'closes_at' => $closes,
        ]);
    }

    return $branch->fresh();
}

it('is open around the clock when no hours are set', function () {
    $branch = branchWithHours();

    expect($branch->state(doha('2026-10-11 03:00')))->toBe(BranchState::Open);
});

it('follows split shifts', function () {
    $branch = branchWithHours([[0, '07:00', '11:00'], [0, '16:00', '23:00']]);

    expect($branch->state(doha('2026-10-11 08:30')))->toBe(BranchState::Open)
        ->and($branch->state(doha('2026-10-11 13:00')))->toBe(BranchState::Closed)
        ->and($branch->state(doha('2026-10-11 16:00')))->toBe(BranchState::Open)
        ->and($branch->state(doha('2026-10-11 23:00')))->toBe(BranchState::Closed);
});

it('keeps a late shift open after midnight into the next day', function () {
    // Sunday 18:00 → Monday 02:00.
    $branch = branchWithHours([[0, '18:00', '02:00']]);

    expect($branch->state(doha('2026-10-11 23:30')))->toBe(BranchState::Open)
        ->and($branch->state(doha('2026-10-12 01:59')))->toBe(BranchState::Open)
        ->and($branch->state(doha('2026-10-12 02:00')))->toBe(BranchState::Closed)
        ->and(app(BranchSchedule::class)->closesAt($branch, doha('2026-10-11 23:30'))?->format('Y-m-d H:i'))->toBe('2026-10-12 02:00');
});

it('uses the restaurant timezone, not the server one', function () {
    $branch = branchWithHours([[0, '07:00', '11:00']]);

    // 05:30 UTC is 08:30 in Doha.
    expect($branch->state(CarbonImmutable::parse('2026-10-11 05:30', 'UTC')))->toBe(BranchState::Open);
});

it('closes on holidays and finds the next opening after them', function () {
    $branch = branchWithHours([[0, '07:00', '23:00'], [1, '07:00', '23:00'], [2, '07:00', '23:00']]);

    BranchClosure::withoutTenancy()->create([
        'restaurant_id' => $branch->restaurant_id,
        'branch_id' => $branch->id,
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-10-12',
        'reason' => ['en' => 'National Day', 'ar' => 'اليوم الوطني'],
    ]);
    $branch->refresh();

    expect($branch->state(doha('2026-10-11 12:00')))->toBe(BranchState::Holiday)
        ->and(app(BranchSchedule::class)->nextOpening($branch, doha('2026-10-11 12:00'))?->format('Y-m-d H:i'))->toBe('2026-10-13 07:00');
});

it('lets the on/off switch, a pause and busy mode win over the hours', function () {
    $now = doha('2026-10-11 12:00');

    expect(branchWithHours(attributes: ['is_open' => false])->state($now))->toBe(BranchState::Disabled)
        ->and(branchWithHours(attributes: ['paused_until' => $now->addMinutes(20)->utc()])->state($now))->toBe(BranchState::Paused)
        ->and(branchWithHours(attributes: ['paused_until' => $now->subMinute()->utc()])->state($now))->toBe(BranchState::Open);

    $busy = branchWithHours(attributes: ['busy_extra_minutes' => 15, 'busy_until' => $now->addHour()->utc(), 'avg_prep_minutes' => 8]);

    expect($busy->state($now))->toBe(BranchState::Busy)
        ->and($busy->state($now)->acceptsOrders())->toBeTrue()
        ->and($busy->currentPrepMinutes($now))->toBe(23)
        ->and($busy->currentPrepMinutes($now->addHours(2)))->toBe(8);
});

it('finds the next opening later the same day', function () {
    $branch = branchWithHours([[0, '07:00', '11:00'], [0, '16:00', '23:00']]);

    expect(app(BranchSchedule::class)->nextOpening($branch, doha('2026-10-11 12:00'))?->format('H:i'))->toBe('16:00');
});

it('knows when a paused branch takes orders again', function () {
    $branch = branchWithHours([[0, '07:00', '11:00'], [0, '16:00', '23:00']], [
        'paused_until' => doha('2026-10-11 10:30')->utc(),
    ]);
    $schedule = app(BranchSchedule::class);

    // Pause ends inside the morning shift: back when the pause ends.
    expect($schedule->reopensAt($branch, doha('2026-10-11 10:00'))?->format('H:i'))->toBe('10:30');

    // Pause outlasts the morning shift: back when the evening shift opens.
    $branch->forceFill(['paused_until' => doha('2026-10-11 12:00')->utc()])->save();
    expect($schedule->reopensAt($branch->fresh(), doha('2026-10-11 10:00'))?->format('H:i'))->toBe('16:00');

    // Switched off: no automatic reopening.
    $branch->forceFill(['is_open' => false])->save();
    expect($schedule->reopensAt($branch->fresh(), doha('2026-10-11 10:00')))->toBeNull();
});
