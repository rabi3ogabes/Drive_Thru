<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\PointsRedemption;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\CartPricer;
use App\Domain\Ordering\Data\Adjustment;
use App\Domain\Ordering\Data\CheckoutBenefits;
use App\Domain\Ordering\Data\PricedCart;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;

/** @return array{0: Customer, 1: Branch, 2: Product} */
function benefitsSetup(array $restaurant = ['tax_rate_bp' => 500, 'service_fee_bp' => 0]): array
{
    $customer = loyaltyCustomer();
    $customer->restaurant->forceFill($restaurant)->save();
    $branch = app(CreateBranch::class)->handle($customer->restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $product = Product::factory()->create(['restaurant_id' => $customer->restaurant_id, 'price_minor' => 10000]);

    return [$customer, $branch, $product];
}

function priceWith(Branch $branch, Product $product, ?CheckoutBenefits $benefits, int $quantity = 1): PricedCart
{
    return app(CartPricer::class)->price($branch->fresh(), [['key' => 'a', 'product_id' => $product->id, 'quantity' => $quantity]], null, $benefits);
}

it('applies coupon, then reward, then points, and taxes the net', function () {
    [$customer, $branch, $product] = benefitsSetup();
    $promo = Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => '10%', 'ar' => '10%'], 'type' => PromotionType::Percent, 'value_bp' => 1000, 'requires_coupon' => true]);
    Coupon::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'promotion_id' => $promo->id, 'code' => 'TEN']);
    $wallet = app(GrantReward::class)->handle($customer, Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => '20 off', 'ar' => '20'], 'type' => RewardType::FixedOff, 'value' => 2000]), 'manual', null);
    app(Points::class)->credit($customer, LedgerType::Earn, 1000, null, null);

    $cart = priceWith($branch, $product, new CheckoutBenefits($customer, 'ten', $wallet->id, 1000));

    // 100.00 − 10.00 coupon − 20.00 reward = 70.00; points worth 10.00 (cap 35.00) → net 60.00, tax 5% = 3.00.
    expect(array_map(fn (Adjustment $a) => [$a->kind, $a->amountMinor], $cart->adjustments))->toBe([['coupon', 1000], ['reward', 2000], ['points', 1000]])
        ->and($cart->discountMinor)->toBe(4000)
        ->and($cart->pointsRedeemed)->toBe(1000)
        ->and($cart->taxMinor)->toBe(300)
        ->and($cart->totalMinor)->toBe(6300)
        ->and($cart->benefitError)->toBeNull();
});

it('caps points at the program limit and the balance, in whole steps', function () {
    [$customer, $branch, $product] = benefitsSetup();
    app(Points::class)->credit($customer, LedgerType::Earn, 9999, null, null);
    $program = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $customer->restaurant_id)->sole();
    $clamp = app(PointsRedemption::class);

    expect($clamp->clamp($program, $customer->fresh(), 99999, 10000))->toBe(['points' => 5000, 'discountMinor' => 5000])
        ->and($clamp->clamp($program, $customer->fresh(), 250, 10000))->toBe(['points' => 200, 'discountMinor' => 200])
        ->and($clamp->clamp($program, $customer->fresh(), 50, 10000))->toBe(['points' => 0, 'discountMinor' => 0]);

    $program->update(['is_enabled' => false]);
    expect($clamp->clamp($program->fresh(), $customer->fresh(), 1000, 10000)['points'])->toBe(0);
});

it('applies automatic promotions without benefits, and estimates points to earn', function () {
    [$customer, $branch, $product] = benefitsSetup();
    Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Happy', 'ar' => 'Happy'], 'type' => PromotionType::Fixed, 'value_minor' => 1000]);

    expect(priceWith($branch, $product, null)->discountMinor)->toBe(1000)
        ->and(priceWith($branch, $product, new CheckoutBenefits($customer))->pointsToEarn)->toBe(90);
});

it('explains an unknown coupon and still prices the cart', function () {
    [$customer, $branch, $product] = benefitsSetup();

    $cart = priceWith($branch, $product, new CheckoutBenefits($customer, 'NOPE'));

    expect($cart->benefitError)->toBe(__('promotions.coupon_errors.unknown'))
        ->and($cart->totalMinor)->toBe(10500);
});

it('ignores promotions, rewards and points when the plan lacks them', function () {
    [$customer, $branch, $product] = benefitsSetup();
    $customer->restaurant->forceFill(['feature_overrides' => ['loyalty' => false, 'promotions' => false]])->save();
    Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'X', 'ar' => 'X'], 'type' => PromotionType::Fixed, 'value_minor' => 1000]);
    app(Points::class)->credit($customer, LedgerType::Earn, 1000, null, null);

    $cart = priceWith($branch, $product, new CheckoutBenefits($customer, 'ANY', null, 1000));

    expect($cart->discountMinor)->toBe(0)
        ->and($cart->pointsToEarn)->toBe(0)
        ->and($cart->benefitError)->toBe(__('promotions.coupon_errors.unavailable'));
});

it('never goes negative and never discounts more than the subtotal', function () {
    mt_srand(23);
    [$customer, $branch, $product] = benefitsSetup(['tax_rate_bp' => 500, 'service_fee_bp' => 250]);
    app(Points::class)->credit($customer, LedgerType::Earn, 100000, null, null);
    $products = collect(range(1, 4))->map(fn () => Product::factory()->create(['restaurant_id' => $customer->restaurant_id, 'price_minor' => mt_rand(100, 4000)]));

    foreach (range(1, 300) as $i) {
        Promotion::withoutTenancy()->delete();
        Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'P', 'ar' => 'P'], 'type' => PromotionType::Fixed, 'value_minor' => mt_rand(0, 9000), 'stackable' => true]);
        Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Q', 'ar' => 'Q'], 'type' => PromotionType::Percent, 'value_bp' => mt_rand(0, 10000), 'stackable' => true]);
        $lines = $products->random(mt_rand(1, 4))->map(fn (Product $p, int $k) => ['key' => (string) $k, 'product_id' => $p->id, 'quantity' => mt_rand(1, 3)])->values()->all();

        $cart = app(CartPricer::class)->price($branch->fresh(), $lines, null, new CheckoutBenefits($customer->fresh(), null, null, mt_rand(0, 20000)));

        expect($cart->discountMinor)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($cart->subtotalMinor)
            ->and($cart->totalMinor)->toBeGreaterThanOrEqual(0)
            ->and($cart->totalMinor)->toBe($cart->subtotalMinor - $cart->discountMinor + $cart->serviceFeeMinor + $cart->taxMinor);
    }
});

it('caps points on what is left after the code and the reward', function () {
    [$customer, $branch, $product] = benefitsSetup();
    LoyaltyProgram::withoutTenancy()->where('restaurant_id', $customer->restaurant_id)->update(['max_redeem_percent' => 30]);
    $wallet = app(GrantReward::class)->handle($customer, Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => '50 off', 'ar' => '50'], 'type' => RewardType::FixedOff, 'value' => 5000]), 'manual', null);
    app(Points::class)->credit($customer, LedgerType::Earn, 99999, null, null);

    $cart = priceWith($branch, $product, new CheckoutBenefits($customer, null, $wallet->id, 99999));

    // 100.00 − 50.00 reward = 50.00 left; 30% = 15.00 → 1,500 points.
    expect($cart->pointsRedeemed)->toBe(1500)->and($cart->discountMinor)->toBe(6500);
});
