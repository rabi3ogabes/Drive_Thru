<?php

use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Exceptions\NotEnoughPoints;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Points;

it('credits lots and debits the soonest-expiring first', function () {
    $customer = loyaltyCustomer();
    $points = app(Points::class);

    $later = $points->credit($customer, LedgerType::Bonus, 100, null, 'later');
    $soon = $points->credit($customer, LedgerType::Bonus, 100, null, 'soon');
    $soon->forceFill(['expires_at' => now()->addMonth()])->save();

    $points->debit($customer, LedgerType::Redeem, 150, null, 'spend');

    expect($soon->fresh()->remaining)->toBe(0)
        ->and($later->fresh()->remaining)->toBe(50)
        ->and($customer->fresh()->points_balance)->toBe(50);
});

it('refuses to spend more than the balance and changes nothing', function () {
    $customer = loyaltyCustomer();
    app(Points::class)->credit($customer, LedgerType::Bonus, 80, null, null);

    expect(fn () => app(Points::class)->debit($customer, LedgerType::Redeem, 100, null, null))->toThrow(NotEnoughPoints::class);
    expect($customer->fresh()->points_balance)->toBe(80)
        ->and(LedgerEntry::withoutTenancy()->count())->toBe(1);
});

it('keeps the balance equal to the ledger and to the unspent lots', function () {
    mt_srand(7);
    $customer = loyaltyCustomer();
    $points = app(Points::class);

    foreach (range(1, 300) as $i) {
        $balance = $customer->fresh()->points_balance;
        $amount = mt_rand(1, 250);

        if (mt_rand(0, 2) > 0 || $balance === 0) {
            $points->credit($customer, LedgerType::Bonus, $amount, null, null);
        } else {
            $points->debit($customer, LedgerType::Redeem, min($amount, $balance), null, null);
        }

        $fresh = $customer->fresh();
        expect($fresh->points_balance)->toBe((int) LedgerEntry::withoutTenancy()->where('customer_id', $customer->id)->sum('points'))
            ->and($fresh->points_balance)->toBe((int) LedgerEntry::withoutTenancy()->where('customer_id', $customer->id)->sum('remaining'));
    }
});

it('stays inactive until the owner sets every value', function () {
    $restaurant = restaurant();
    $program = LoyaltyProgram::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'is_enabled' => true]);

    expect($program->isActive())->toBeFalse();

    $program->forceFill(['earn_points_per_unit' => 1, 'redeem_points' => 100, 'redeem_value_minor' => 100, 'max_redeem_percent' => 50, 'min_redeem_points' => 100])->save();
    expect($program->fresh()->isActive())->toBeTrue()
        ->and($program->pointsFor(5050, 'QAR'))->toBe(50)
        ->and($program->valueOf(1000))->toBe(1000)
        ->and($program->pointsForValue(250))->toBe(300);
});

it('gives points an expiry from the program', function () {
    $customer = loyaltyCustomer(program: ['expiry_months' => 6]);

    $entry = app(Points::class)->credit($customer, LedgerType::Earn, 10, null, null);

    expect($entry->expires_at->diffInDays(now()->addMonths(6), true))->toBeLessThan(1);
});
