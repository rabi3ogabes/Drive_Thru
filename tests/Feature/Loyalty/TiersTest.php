<?php

use App\Domain\Loyalty\EarnRules;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Models\EarnRule;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\Tiers;

function tiersFor(int $restaurantId): array
{
    return collect([['Bronze', 0], ['Silver', 400], ['Gold', 1000]])->map(fn (array $t, int $i) => Tier::withoutTenancy()->create([
        'restaurant_id' => $restaurantId, 'name' => ['en' => $t[0], 'ar' => $t[0]], 'threshold_points' => $t[1], 'sort' => $i,
    ]))->all();
}

it('promotes on points earned in the window and demotes when they age out', function () {
    $customer = loyaltyCustomer();
    [$bronze, $silver] = tiersFor($customer->restaurant_id);

    app(Points::class)->credit($customer, LedgerType::Earn, 500, null, null);
    expect(app(Tiers::class)->recompute($customer)?->id)->toBe($silver->id)
        ->and($customer->fresh()->tier_id)->toBe($silver->id);

    $this->travel(13)->months();
    expect(app(Tiers::class)->recompute($customer->fresh())?->id)->toBe($bronze->id);
});

it('does not count returned or adjusted points towards tiers, and subtracts reversals', function () {
    $customer = loyaltyCustomer();
    tiersFor($customer->restaurant_id);
    $points = app(Points::class);

    $points->credit($customer, LedgerType::Earn, 600, null, null);
    $points->credit($customer, LedgerType::Returned, 900, null, null);
    $points->credit($customer, LedgerType::Adjust, 900, null, null);
    $points->debit($customer, LedgerType::Reverse, 300, null, null);

    expect(app(Tiers::class)->qualifyingPoints($customer))->toBe(300);
});

it('reports progress to the next tier', function () {
    $customer = loyaltyCustomer();
    [, $silver, $gold] = tiersFor($customer->restaurant_id);
    app(Points::class)->credit($customer, LedgerType::Earn, 700, null, null);
    app(Tiers::class)->recompute($customer);

    $progress = app(Tiers::class)->progress($customer->fresh());

    expect($progress['tier']->id)->toBe($silver->id)
        ->and($progress['next']->id)->toBe($gold->id)
        ->and($progress['points'])->toBe(700)
        ->and($progress['needed'])->toBe(300)
        ->and($progress['percent'])->toBe(50);
});

it('creates the four standard tiers with empty thresholds for the owner to set', function () {
    $restaurant = restaurant();
    $program = LoyaltyProgram::withoutTenancy()->create(['restaurant_id' => $restaurant->id]);

    app(Tiers::class)->standardPreset($program);

    expect(Tier::withoutTenancy()->where('restaurant_id', $restaurant->id)->orderBy('sort')->get()->map(fn (Tier $t) => [$t->getTranslation('name', 'en'), $t->threshold_points])->all())
        ->toBe([['Bronze', 0], ['Silver', 0], ['Gold', 0], ['Platinum', 0]]);
});

it('uses the strongest matching earn boost, preferring product over category over branch', function () {
    $restaurant = restaurant();
    $rules = collect([
        new EarnRule(['restaurant_id' => $restaurant->id, 'scope' => 'branch', 'scope_id' => 1, 'multiplier_bp' => 30000]),
        new EarnRule(['restaurant_id' => $restaurant->id, 'scope' => 'category', 'scope_id' => 5, 'multiplier_bp' => 20000]),
        new EarnRule(['restaurant_id' => $restaurant->id, 'scope' => 'product', 'scope_id' => 9, 'multiplier_bp' => 20000, 'ends_at' => now()->subDay()]),
    ]);
    $earn = app(EarnRules::class);

    expect($earn->multiplier($rules, 9, 5, 1, now()))->toBe(30000)
        ->and($earn->multiplier($rules, 9, 5, 2, now()))->toBe(20000)
        ->and($earn->multiplier($rules, 8, 6, 2, now()))->toBe(10000);
});
