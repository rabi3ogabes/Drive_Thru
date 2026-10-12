<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Integrations\Api\OrderPayload;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\CartPricer;
use App\Domain\Ordering\Data\CheckoutBenefits;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Exceptions\OrderRejected;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderAdjustment;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\Redemption;
use Illuminate\Support\Str;

/** @return array{0: Customer, 1: Branch, 2: Product} */
function benefitOrderSetup(): array
{
    $customer = loyaltyCustomer();
    $branch = app(CreateBranch::class)->handle($customer->restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $product = Product::factory()->create(['restaurant_id' => $customer->restaurant_id, 'price_minor' => 10000]);

    return [$customer, $branch, $product];
}

function orderWith(Customer $customer, Branch $branch, Product $product, ?CheckoutBenefits $benefits): Order
{
    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer->fresh(), branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
        benefits: $benefits,
    ));
}

function couponFor(Customer $customer, array $promotion = [], array $coupon = []): Coupon
{
    $promo = Promotion::withoutTenancy()->create(array_merge(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => '10 off', 'ar' => '10'], 'type' => PromotionType::Fixed, 'value_minor' => 1000, 'requires_coupon' => true], $promotion));

    return Coupon::withoutTenancy()->create(array_merge(['restaurant_id' => $customer->restaurant_id, 'promotion_id' => $promo->id, 'code' => 'SAVE'], $coupon));
}

it('places an order with a coupon, a reward and points, and records each', function () {
    [$customer, $branch, $product] = benefitOrderSetup();
    $coupon = couponFor($customer);
    $wallet = app(GrantReward::class)->handle($customer, Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => '5 off', 'ar' => '5'], 'type' => RewardType::FixedOff, 'value' => 500]), 'manual', null);
    app(Points::class)->credit($customer, LedgerType::Earn, 1000, null, null);

    $order = orderWith($customer, $branch, $product, new CheckoutBenefits($customer, 'save', $wallet->id, 1000));

    expect($order)->discount_minor->toBe(2500)->total_minor->toBe(7500)->points_redeemed->toBe(1000)
        ->and(OrderAdjustment::withoutTenancy()->where('order_id', $order->id)->orderBy('id')->pluck('kind')->all())->toBe(['coupon', 'reward', 'points'])
        ->and(Redemption::withoutTenancy()->sole())->coupon_id->toBe($coupon->id)->discount_minor->toBe(1000)
        ->and($coupon->fresh()->used_count)->toBe(1)
        ->and($coupon->promotion->fresh()->used_count)->toBe(1)
        ->and($wallet->fresh())->status->toBe(WalletReward::USED)->used_order_id->toBe($order->id)
        ->and($customer->fresh()->points_balance)->toBe(0)
        ->and(LedgerEntry::withoutTenancy()->where('type', LedgerType::Redeem->value)->sole()->order_id)->toBe($order->id)
        ->and(OrderPayload::from($order->fresh())['adjustments'])->toHaveCount(3);
});

it('applies automatic promotions to every order', function () {
    [$customer, $branch, $product] = benefitOrderSetup();
    Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Lunch', 'ar' => 'غداء'], 'type' => PromotionType::Percent, 'value_bp' => 2000]);

    $order = orderWith($customer, $branch, $product, null);

    expect($order->discount_minor)->toBe(2000)
        ->and(Redemption::withoutTenancy()->sole()->coupon_id)->toBeNull();
});

it('lets only one of two orders spend the same points', function () {
    [$customer, $branch, $product] = benefitOrderSetup();
    app(Points::class)->credit($customer, LedgerType::Earn, 100, null, null);

    orderWith($customer, $branch, $product, new CheckoutBenefits($customer, null, null, 100));

    expect(fn () => orderWith($customer, $branch, $product, new CheckoutBenefits($customer, null, null, 100)))
        ->toThrow(OrderRejected::class, __('ordering.errors.benefit_changed'));
    expect($customer->fresh()->points_balance)->toBe(0)
        ->and(LedgerEntry::withoutTenancy()->where('type', LedgerType::Redeem->value)->count())->toBe(1)
        ->and(Order::withoutTenancy()->count())->toBe(1);
});

it('gives the last use of a coupon to one order only', function () {
    [$customer, $branch, $product] = benefitOrderSetup();
    $coupon = couponFor($customer, coupon: ['usage_limit' => 1]);
    $other = Customer::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'phone' => '+97455550099']);

    orderWith($customer, $branch, $product, new CheckoutBenefits($customer, 'SAVE'));

    expect(fn () => orderWith($other, $branch, $product, new CheckoutBenefits($other, 'SAVE')))->toThrow(OrderRejected::class);
    expect($coupon->fresh()->used_count)->toBe(1);
});

it('re-prices at placement: a promotion archived after the guest looked is not applied', function () {
    [$customer, $branch, $product] = benefitOrderSetup();
    $promo = Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'X', 'ar' => 'X'], 'type' => PromotionType::Fixed, 'value_minor' => 1000]);
    $seen = app(CartPricer::class)->price($branch, [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]], null, new CheckoutBenefits($customer));
    $promo->update(['archived_at' => now()]);

    $order = orderWith($customer, $branch, $product, null);

    expect($seen->discountMinor)->toBe(1000)->and($order->discount_minor)->toBe(0)->and($order->total_minor)->toBe(10000);
});

it('does not know another restaurant\'s coupons', function () {
    [$customer, $branch, $product] = benefitOrderSetup();
    $foreign = loyaltyCustomer();
    couponFor($foreign, coupon: ['code' => 'THEIRS']);

    expect(fn () => orderWith($customer, $branch, $product, new CheckoutBenefits($customer, 'THEIRS')))->toThrow(OrderRejected::class);
});
