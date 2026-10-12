<?php

use App\Domain\Loyalty\Actions\ClaimReward;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\RewardPricer;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Data\PricedLine;

function reward(int $restaurantId, RewardType $type, array $attributes = []): Reward
{
    return Reward::withoutTenancy()->create(array_merge([
        'restaurant_id' => $restaurantId, 'name' => ['en' => 'Treat', 'ar' => 'مكافأة'], 'type' => $type, 'points_cost' => 300,
    ], $attributes));
}

it('claims a reward with points into the wallet', function () {
    $customer = loyaltyCustomer();
    app(Points::class)->credit($customer, LedgerType::Earn, 500, null, null);
    $reward = reward($customer->restaurant_id, RewardType::FixedOff, ['value' => 1000, 'valid_days' => 14, 'stock' => 2]);

    $wallet = app(ClaimReward::class)->handle($customer, $reward);

    expect($wallet)->status->toBe(WalletReward::AVAILABLE)->source->toBe('claimed')
        ->and($wallet->expires_at->diffInDays(now()->addDays(14), true))->toBeLessThan(1)
        ->and($customer->fresh()->points_balance)->toBe(200)
        ->and($reward->fresh()->stock)->toBe(1);
});

it('refuses claims without enough points, out of stock, inactive or bonus rewards, changing nothing', function () {
    $customer = loyaltyCustomer();
    app(Points::class)->credit($customer, LedgerType::Earn, 200, null, null);
    $claim = app(ClaimReward::class);

    expect(fn () => $claim->handle($customer, reward($customer->restaurant_id, RewardType::FixedOff, ['value' => 100])))->toThrow(RewardUnavailable::class)
        ->and(fn () => $claim->handle($customer, reward($customer->restaurant_id, RewardType::FixedOff, ['value' => 100, 'points_cost' => 100, 'stock' => 0])))->toThrow(RewardUnavailable::class)
        ->and(fn () => $claim->handle($customer, reward($customer->restaurant_id, RewardType::FixedOff, ['value' => 100, 'points_cost' => 100, 'is_active' => false])))->toThrow(RewardUnavailable::class)
        ->and(fn () => $claim->handle($customer, reward($customer->restaurant_id, RewardType::BonusPoints, ['value' => 100, 'points_cost' => 100])))->toThrow(RewardUnavailable::class);

    expect($customer->fresh()->points_balance)->toBe(200)->and(WalletReward::withoutTenancy()->count())->toBe(0);
});

it('refuses a reward from another restaurant', function () {
    $customer = loyaltyCustomer();
    app(Points::class)->credit($customer, LedgerType::Earn, 500, null, null);
    $foreign = reward(restaurant()->id, RewardType::FixedOff, ['value' => 100]);

    expect(fn () => app(ClaimReward::class)->handle($customer, $foreign))->toThrow(RewardUnavailable::class);
});

it('grants bonus points straight into the balance', function () {
    $customer = loyaltyCustomer();
    $bonus = reward($customer->restaurant_id, RewardType::BonusPoints, ['value' => 250, 'points_cost' => null]);

    $wallet = app(GrantReward::class)->handle($customer, $bonus, 'birthday', null);

    expect($wallet->status)->toBe(WalletReward::USED)
        ->and($customer->fresh()->points_balance)->toBe(250);
});

it('prices wallet rewards against the cart', function () {
    $customer = loyaltyCustomer();
    $fries = Product::factory()->create(['restaurant_id' => $customer->restaurant_id, 'price_minor' => 1200]);
    $burger = Product::factory()->create(['restaurant_id' => $customer->restaurant_id, 'price_minor' => 3000]);
    $lines = [new PricedLine('a', $burger, 1, 3000, 0, [], null), new PricedLine('b', $fries, 2, 1200, 0, [], null)];
    $pricer = app(RewardPricer::class);
    $grant = fn (Reward $r) => app(GrantReward::class)->handle($customer, $r, 'manual', null);

    expect($pricer->adjustment($grant(reward($customer->restaurant_id, RewardType::FreeItem, ['product_id' => $fries->id])), $lines, 5400)?->amountMinor)->toBe(1200)
        ->and($pricer->adjustment($grant(reward($customer->restaurant_id, RewardType::FreeItem, ['product_id' => Product::factory()->create(['restaurant_id' => $customer->restaurant_id])->id])), $lines, 5400))->toBeNull()
        ->and($pricer->adjustment($grant(reward($customer->restaurant_id, RewardType::PercentOff, ['value' => 2500])), $lines, 4000)?->amountMinor)->toBe(1000)
        ->and($pricer->adjustment($grant(reward($customer->restaurant_id, RewardType::FixedOff, ['value' => 9000])), $lines, 4000)?->amountMinor)->toBe(4000)
        ->and($pricer->adjustment($grant(reward($customer->restaurant_id, RewardType::FixedOff, ['value' => 500, 'min_order_minor' => 6000])), $lines, 5400))->toBeNull();
});
