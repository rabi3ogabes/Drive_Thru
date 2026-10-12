<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\EarnRule;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\CheckoutBenefits;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Actions\CompletePayment;
use App\Domain\Payments\Actions\RefundPayment;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\Redemption;
use Illuminate\Support\Str;

/** @return array{0: Customer, 1: Branch, 2: Product} */
function lifecycleSetup(): array
{
    config(['payments.fake_enabled' => true]);
    $customer = loyaltyCustomer();
    $branch = app(CreateBranch::class)->handle($customer->restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $product = Product::factory()->create(['restaurant_id' => $customer->restaurant_id, 'price_minor' => 10000]);

    return [$customer, $branch, $product];
}

function lifecycleOrder(Customer $customer, Branch $branch, Product $product, ?CheckoutBenefits $benefits = null, PaymentMethod $method = PaymentMethod::PayAtPickup, int $quantity = 1): Order
{
    $order = app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer->fresh(), branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => $quantity]],
        paymentMethod: $method, idempotencyKey: (string) Str::uuid(), benefits: $benefits,
    ));

    if ($method->isOnline()) {
        app(StartPayment::class)->handle($order, $method);
        app(CompletePayment::class)->handle(Payment::withoutTenancy()->where('order_id', $order->id)->sole(), approved: true);
    }

    return $order->fresh();
}

function pickUp(Order $order): Order
{
    $transition = app(TransitionOrder::class);
    $transition->handle($order->fresh(), OrderStatus::Preparing, 'staff:1');
    $transition->handle($order->fresh(), OrderStatus::Ready, 'staff:1');
    $order->fresh()->forceFill(['payment_status' => 'paid'])->save();
    $transition->handle($order->fresh(), OrderStatus::PickedUp, 'staff:1');

    return $order->fresh();
}

it('awards points once at pick-up, with earn boosts, tier multiplier and bonus points', function () {
    [$customer, $branch, $product] = lifecycleSetup();
    EarnRule::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'scope' => 'product', 'scope_id' => $product->id, 'multiplier_bp' => 20000]);
    $tier = Tier::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Base', 'ar' => 'Base'], 'threshold_points' => 0, 'earn_multiplier_bp' => 15000]);
    $customer->forceFill(['tier_id' => $tier->id])->save();
    Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Bonus', 'ar' => 'Bonus'], 'type' => PromotionType::BonusPoints, 'value_minor' => 25]);

    $order = pickUp(lifecycleOrder($customer, $branch, $product));
    event(new OrderStatusChanged($order, OrderStatus::Ready, OrderStatus::PickedUp));

    // 100 QAR × 1 point × 2 (burger boost) × 1.5 (tier) = 300, + 25 bonus.
    expect($order->points_earned)->toBe(325)
        ->and($customer->fresh()->points_balance)->toBe(325)
        ->and(LedgerEntry::withoutTenancy()->where('order_id', $order->id)->where('type', LedgerType::Earn->value)->count())->toBe(1);
});

it('never awards points for a cancelled order', function () {
    [$customer, $branch, $product] = lifecycleSetup();

    $order = lifecycleOrder($customer, $branch, $product);
    app(CancelOrder::class)->byStaff($order, 'staff', 1, 'Customer asked');

    expect($customer->fresh()->points_balance)->toBe(0)->and($order->fresh()->points_earned)->toBe(0);
});

it('gives back points, the reward and the coupon use when an order is cancelled', function () {
    [$customer, $branch, $product] = lifecycleSetup();
    app(Points::class)->credit($customer, LedgerType::Earn, 500, null, null);
    $wallet = app(GrantReward::class)->handle($customer, Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'R', 'ar' => 'R'], 'type' => RewardType::FixedOff, 'value' => 500]), 'manual', null);
    $promo = Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'C', 'ar' => 'C'], 'type' => PromotionType::Fixed, 'value_minor' => 500, 'requires_coupon' => true, 'stackable' => true]);
    $coupon = Coupon::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'promotion_id' => $promo->id, 'code' => 'BACK']);

    $order = lifecycleOrder($customer, $branch, $product, new CheckoutBenefits($customer, 'BACK', $wallet->id, 500));
    expect($customer->fresh()->points_balance)->toBe(0);

    app(CancelOrder::class)->byStaff($order, 'staff', 1, 'Kitchen issue');

    expect($customer->fresh()->points_balance)->toBe(500)
        ->and($wallet->fresh())->status->toBe(WalletReward::AVAILABLE)->used_order_id->toBeNull()
        ->and(Redemption::withoutTenancy()->sole()->released_at)->not->toBeNull()
        ->and($coupon->fresh()->used_count)->toBe(0)
        ->and($promo->fresh()->used_count)->toBe(0);

    // The same event again gives nothing back twice.
    event(new OrderStatusChanged($order->fresh(), OrderStatus::Received, OrderStatus::Cancelled));
    expect($customer->fresh()->points_balance)->toBe(500)->and($coupon->fresh()->used_count)->toBe(0);
});

it('takes back earned points when a picked-up order is refunded, in proportion', function () {
    [$customer, $branch, $product] = lifecycleSetup();
    $order = pickUp(lifecycleOrder($customer, $branch, $product, method: PaymentMethod::Card, quantity: 2));
    $payment = Payment::withoutTenancy()->where('order_id', $order->id)->sole();
    expect($order->points_earned)->toBe(200);

    app(RefundPayment::class)->handle($payment, 10000, 'One item wrong', 'staff:1');
    expect($customer->fresh()->points_balance)->toBe(100);

    app(RefundPayment::class)->handle($payment->fresh(), null, 'Rest', 'staff:1');
    expect($customer->fresh()->points_balance)->toBe(0)
        ->and((int) LedgerEntry::withoutTenancy()->where('type', LedgerType::Reverse->value)->sum('points'))->toBe(-200);
});

it('expires old points and wallet rewards, keeps fresh ones, and recomputes tiers', function () {
    [$customer] = lifecycleSetup();
    $points = app(Points::class);
    $old = $points->credit($customer, LedgerType::Earn, 300, null, null);
    $old->forceFill(['expires_at' => now()->subDay()])->save();
    $points->credit($customer, LedgerType::Earn, 200, null, null);
    $wallet = app(GrantReward::class)->handle($customer, Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'R', 'ar' => 'R'], 'type' => RewardType::FixedOff, 'value' => 500, 'valid_days' => 1]), 'manual', null);
    $gold = Tier::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Gold', 'ar' => 'Gold'], 'threshold_points' => 100]);

    $this->travel(2)->days();
    $this->artisan('loyalty:maintain')->assertSuccessful();
    $this->artisan('loyalty:maintain')->assertSuccessful();

    expect($customer->fresh())->points_balance->toBe(200)->tier_id->toBe($gold->id)
        ->and((int) LedgerEntry::withoutTenancy()->where('type', LedgerType::Expire->value)->sum('points'))->toBe(-300)
        ->and($wallet->fresh()->status)->toBe(WalletReward::EXPIRED);
});

it('returns the balance to where it started after earning and fully refunding', function () {
    mt_srand(31);
    [$customer, $branch] = lifecycleSetup();

    foreach (range(1, 20) as $i) {
        $product = Product::factory()->create(['restaurant_id' => $customer->restaurant_id, 'price_minor' => mt_rand(500, 20000)]);
        $before = $customer->fresh()->points_balance;
        $order = pickUp(lifecycleOrder($customer, $branch, $product, method: PaymentMethod::Card, quantity: mt_rand(1, 3)));
        app(RefundPayment::class)->handle(Payment::withoutTenancy()->where('order_id', $order->id)->sole(), null, 'test', 'system');

        expect($customer->fresh()->points_balance)->toBe($before);
    }
});
