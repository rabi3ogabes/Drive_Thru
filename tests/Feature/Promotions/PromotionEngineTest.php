<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Data\Adjustment;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Data\PricedLine;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\Redemption;
use App\Domain\Promotions\PromotionEngine;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function promoBranch(): Branch
{
    $restaurant = restaurant();
    $restaurant->forceFill(['timezone' => 'Asia/Qatar'])->save();

    return app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
}

function promoLine(Branch $branch, int $price, int $quantity = 1, ?int $categoryId = null, ?Product $product = null): PricedLine
{
    $product ??= Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => $price, 'menu_category_id' => $categoryId]);

    return new PricedLine('k'.$product->id.'-'.random_int(1, 99999), $product, $quantity, $price, 0, [], null);
}

function promo(Branch $branch, PromotionType $type, array $attributes = []): Promotion
{
    return Promotion::withoutTenancy()->create(array_merge([
        'restaurant_id' => $branch->restaurant_id,
        'name' => ['en' => $type->value, 'ar' => $type->value],
        'type' => $type,
    ], $attributes));
}

function promoCustomer(Branch $branch, array $attributes = []): Customer
{
    return Customer::withoutTenancy()->create(array_merge(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455'.random_int(100000, 999999)], $attributes));
}

/** A real order to hang a redemption on. */
function placeholderOrderId(Branch $branch, Customer $customer): int
{
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 1000]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ))->id;
}

/** @return list<Adjustment> */
function applyPromos(Branch $branch, array $lines, ?Customer $customer = null, ?Coupon $coupon = null, ?CarbonImmutable $at = null): array
{
    return app(PromotionEngine::class)->apply($branch, $lines, $customer, $coupon, $at ?? CarbonImmutable::now())['adjustments'];
}

function discountOf(array $adjustments): int
{
    return array_sum(array_map(fn (Adjustment $a): int => $a->amountMinor, $adjustments));
}

it('computes each promotion type', function () {
    $branch = promoBranch();
    $burger = promoLine($branch, 2500, 2);
    $fries = promoLine($branch, 1000, 3);
    $lines = [$burger, $fries];

    expect(discountOf(applyPromos($branch, $lines)))->toBe(0);

    $percent = promo($branch, PromotionType::Percent, ['value_bp' => 1000]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(800);
    $percent->delete();

    $fixed = promo($branch, PromotionType::Fixed, ['value_minor' => 99999]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(8000);
    $fixed->delete();

    // Buy 2 get 1 on fries (scope products): 3 fries → the cheapest one free.
    $bogo = promo($branch, PromotionType::Bogo, ['buy_qty' => 2, 'get_qty' => 1, 'scope' => 'products', 'scope_ids' => [$fries->product->id]]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(1000);
    $bogo->delete();

    $free = promo($branch, PromotionType::FreeItem, ['free_product_id' => $fries->product->id]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(1000)
        ->and(discountOf(applyPromos($branch, [$burger])))->toBe(0);
    $free->delete();

    $combo = promo($branch, PromotionType::ComboPrice, ['combo_product_ids' => [$burger->product->id, $fries->product->id], 'combo_price_minor' => 3000]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(500);
    $combo->delete();

    promo($branch, PromotionType::BonusPoints, ['value_minor' => 50]);
    $result = applyPromos($branch, $lines);
    expect(discountOf($result))->toBe(0)->and($result[0]->bonusPoints)->toBe(50);
});

it('applies buy-x-get-y to the cheapest units in each group', function () {
    $branch = promoBranch();
    $category = MenuCategory::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'name' => ['en' => 'Drinks', 'ar' => 'مشروبات']]);
    $lines = [promoLine($branch, 900, 2, $category->id), promoLine($branch, 500, 2, $category->id)];
    promo($branch, PromotionType::Bogo, ['buy_qty' => 1, 'get_qty' => 1, 'scope' => 'categories', 'scope_ids' => [$category->id]]);

    // Units 900, 900, 500, 500 → pairs (900,900) and (500,500) → 900 + 500 free.
    expect(discountOf(applyPromos($branch, $lines)))->toBe(1400);
});

it('applies happy hour in restaurant time', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 1000)];
    promo($branch, PromotionType::Percent, ['value_bp' => 2000, 'time_from' => '18:00', 'time_to' => '20:00']);

    expect(discountOf(applyPromos($branch, $lines, at: CarbonImmutable::parse('2026-10-12 16:30', 'UTC'))))->toBe(200) // 19:30 Doha
        ->and(discountOf(applyPromos($branch, $lines, at: CarbonImmutable::parse('2026-10-12 17:30', 'UTC'))))->toBe(0); // 20:30 Doha
});

it('applies a time range that crosses midnight, and day filters', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 1000)];
    promo($branch, PromotionType::Percent, ['value_bp' => 1000, 'time_from' => '22:00', 'time_to' => '02:00', 'days' => [1]]); // Monday

    expect(discountOf(applyPromos($branch, $lines, at: CarbonImmutable::parse('2026-10-12 20:30', 'UTC'))))->toBe(100) // Mon 23:30 Doha
        ->and(discountOf(applyPromos($branch, $lines, at: CarbonImmutable::parse('2026-10-12 21:30', 'UTC'))))->toBe(100) // Tue 00:30 Doha: still Monday night's window
        ->and(discountOf(applyPromos($branch, $lines, at: CarbonImmutable::parse('2026-10-11 20:30', 'UTC'))))->toBe(0) // Sun 23:30 Doha: not a Monday
        ->and(discountOf(applyPromos($branch, $lines, at: CarbonImmutable::parse('2026-10-12 12:00', 'UTC'))))->toBe(0); // Mon 15:00 Doha
});

it('gives birthday treats within three days, across new year', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 1000)];
    promo($branch, PromotionType::Fixed, ['value_minor' => 300, 'birthday' => true]);
    $guest = promoCustomer($branch, ['birthday' => '1990-12-30']);

    expect(discountOf(applyPromos($branch, $lines, $guest, at: CarbonImmutable::parse('2027-01-02 10:00', 'UTC'))))->toBe(300)
        ->and(discountOf(applyPromos($branch, $lines, $guest, at: CarbonImmutable::parse('2027-01-03 10:00', 'UTC'))))->toBe(0)
        ->and(discountOf(applyPromos($branch, $lines, null, at: CarbonImmutable::parse('2027-01-02 10:00', 'UTC'))))->toBe(0);
});

it('limits first-order promotions to guests who never ordered', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 1000)];
    promo($branch, PromotionType::Fixed, ['value_minor' => 200, 'first_order' => true]);

    expect(discountOf(applyPromos($branch, $lines, promoCustomer($branch))))->toBe(200)
        ->and(discountOf(applyPromos($branch, $lines, promoCustomer($branch, ['orders_count' => 1]))))->toBe(0);
});

it('respects branches, dates, minimum subtotal, archive and total usage limits', function () {
    $branch = promoBranch();
    $other = app(CreateBranch::class)->handle($branch->restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    $lines = [promoLine($branch, 1000)];

    $p = promo($branch, PromotionType::Fixed, ['value_minor' => 100, 'branch_ids' => [$other->id]]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(0);
    $p->update(['branch_ids' => null, 'ends_at' => now()->subMinute()]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(0);
    $p->update(['ends_at' => null, 'min_subtotal_minor' => 1001]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(0);
    $p->update(['min_subtotal_minor' => 1000, 'usage_limit' => 3, 'used_count' => 3]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(0);
    $p->update(['usage_limit' => null, 'archived_at' => now()]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(0);
    $p->update(['archived_at' => null]);
    expect(discountOf(applyPromos($branch, $lines)))->toBe(100);
});

it('lets an exclusive promotion win over a smaller stack and lose to a bigger one', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 10000)];
    promo($branch, PromotionType::Percent, ['value_bp' => 1500]); // exclusive 1500
    $a = promo($branch, PromotionType::Fixed, ['value_minor' => 500, 'stackable' => true]);
    promo($branch, PromotionType::Fixed, ['value_minor' => 600, 'stackable' => true]);

    expect(discountOf(applyPromos($branch, $lines)))->toBe(1500);

    $a->update(['value_minor' => 1000]);
    $result = applyPromos($branch, $lines);
    expect(discountOf($result))->toBe(1600)->and($result)->toHaveCount(2);
});

it('always applies an entered coupon; an exclusive coupon removes automatic promotions', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 10000)];
    promo($branch, PromotionType::Percent, ['value_bp' => 3000, 'stackable' => true]);
    $couponPromo = promo($branch, PromotionType::Fixed, ['value_minor' => 1000, 'requires_coupon' => true]);
    $coupon = Coupon::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'promotion_id' => $couponPromo->id, 'code' => 'SAVE10']);

    expect(discountOf(applyPromos($branch, $lines)))->toBe(3000);

    $result = applyPromos($branch, $lines, coupon: $coupon->fresh('promotion'));
    expect(discountOf($result))->toBe(1000)->and($result[0]->kind)->toBe(Adjustment::COUPON);

    $couponPromo->update(['stackable' => true]);
    expect(discountOf(applyPromos($branch, $lines, coupon: $coupon->fresh('promotion'))))->toBe(4000);
});

it('explains why a coupon does not apply', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 1000)];
    $guest = promoCustomer($branch);
    $p = promo($branch, PromotionType::Fixed, ['value_minor' => 100, 'requires_coupon' => true, 'min_subtotal_minor' => 5000]);
    $coupon = Coupon::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'promotion_id' => $p->id, 'code' => 'BIG', 'per_customer_limit' => 1]);
    $engine = app(PromotionEngine::class);

    expect($engine->apply($branch, $lines, $guest, $coupon->fresh('promotion'), CarbonImmutable::now())['couponError'])->toBe(__('promotions.coupon_errors.min_subtotal', ['amount' => Money::format(5000, 'QAR')]));

    $p->update(['min_subtotal_minor' => 0]);
    Redemption::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'promotion_id' => $p->id, 'coupon_id' => $coupon->id, 'customer_id' => $guest->id, 'order_id' => placeholderOrderId($branch, $guest)]);
    expect($engine->apply($branch, $lines, $guest, $coupon->fresh('promotion'), CarbonImmutable::now())['couponError'])->toBe(__('promotions.coupon_errors.already_used'));

    Redemption::withoutTenancy()->update(['released_at' => now()]);
    expect($engine->apply($branch, $lines, $guest, $coupon->fresh('promotion'), CarbonImmutable::now())['couponError'])->toBeNull();

    $coupon->update(['expires_at' => now()->subMinute()]);
    expect($engine->apply($branch, $lines, $guest, $coupon->fresh('promotion'), CarbonImmutable::now())['couponError'])->toBe(__('promotions.coupon_errors.expired'));
});

it('counts only unreleased redemptions towards per-customer limits', function () {
    $branch = promoBranch();
    $lines = [promoLine($branch, 1000)];
    $guest = promoCustomer($branch);
    $p = promo($branch, PromotionType::Fixed, ['value_minor' => 100, 'per_customer_limit' => 1]);
    $redemption = Redemption::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'promotion_id' => $p->id, 'customer_id' => $guest->id, 'order_id' => placeholderOrderId($branch, $guest)]);

    expect(discountOf(applyPromos($branch, $lines, $guest)))->toBe(0);
    $redemption->update(['released_at' => now()]);
    expect(discountOf(applyPromos($branch, $lines, $guest)))->toBe(100);
});

it('never discounts more than the subtotal and is deterministic', function () {
    mt_srand(11);
    $branch = promoBranch();
    $products = collect(range(1, 6))->map(fn () => Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => mt_rand(100, 5000)]));
    $types = PromotionType::cases();

    foreach (range(1, 500) as $i) {
        Promotion::withoutTenancy()->delete();

        foreach (range(1, mt_rand(1, 4)) as $n) {
            $type = $types[mt_rand(0, count($types) - 1)];
            promo($branch, $type, [
                'value_bp' => mt_rand(100, 10000),
                'value_minor' => mt_rand(50, 20000),
                'buy_qty' => mt_rand(1, 3),
                'get_qty' => mt_rand(1, 2),
                'free_product_id' => $products->random()->id,
                'combo_product_ids' => $products->random(2)->pluck('id')->all(),
                'combo_price_minor' => mt_rand(100, 6000),
                'stackable' => (bool) mt_rand(0, 1),
                'priority' => mt_rand(0, 3),
            ]);
        }

        $lines = $products->random(mt_rand(1, 6))->map(fn (Product $p) => promoLine($branch, $p->price_minor, mt_rand(1, 4), product: $p))->values()->all();
        $subtotal = array_sum(array_map(fn (PricedLine $l) => $l->totalMinor(), $lines));
        $first = applyPromos($branch, $lines);
        $second = applyPromos($branch, $lines);
        $sum = discountOf($first);

        expect($sum)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($subtotal)
            ->and(array_map(fn (Adjustment $a) => [$a->sourceId, $a->amountMinor], $first))->toBe(array_map(fn (Adjustment $a) => [$a->sourceId, $a->amountMinor], $second));

        foreach ($first as $adjustment) {
            expect($adjustment->amountMinor)->toBeGreaterThanOrEqual(0);
        }
    }
});
