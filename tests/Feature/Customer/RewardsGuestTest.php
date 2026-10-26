<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Cart;
use App\Domain\Ordering\Models\Order;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Livewire\Customer\Checkout;
use App\Livewire\Customer\Rewards;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => app()->setLocale('en'));

/** @return array{0: Customer, 1: Branch, 2: Product} */
function rewardsShop(): array
{
    $restaurant = restaurant(slug: 'masar');
    $customer = loyaltyCustomer($restaurant);
    $customer->forceFill(['name' => 'Sara'])->save();
    CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $customer->id, 'plate' => '123456', 'color' => 'White', 'is_default' => true]);
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 5000, 'name' => ['en' => 'Burger', 'ar' => 'برجر']]);

    return [$customer, $branch, $product];
}

function signInRewardsGuest(Customer $customer): void
{
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'customer_id' => $customer->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$customer->restaurant_id}", $token);
    app()->forgetScopedInstances();
}

function checkoutFor(Customer $customer, Branch $branch, Product $product)
{
    app(Cart::class)->add($customer->restaurant_id, $product->id, 1);

    return Livewire::test(Checkout::class, ['restaurant' => $customer->restaurant, 'branch' => $branch->code])->set('payment', 'pay_at_pickup');
}

it('applies a code at checkout, explains a wrong one, and charges the discounted total', function () {
    [$customer, $branch, $product] = rewardsShop();
    signInRewardsGuest($customer);
    $promo = Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Welcome 10%', 'ar' => 'ترحيب 10%'], 'type' => PromotionType::Percent, 'value_bp' => 1000, 'requires_coupon' => true]);
    Coupon::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'promotion_id' => $promo->id, 'code' => 'WELCOME10']);

    $page = checkoutFor($customer, $branch, $product)
        ->set('couponInput', 'nope')->call('applyCoupon')
        ->assertSet('couponCode', null)->assertSee(__('promotions.coupon_errors.unknown'))
        ->set('couponInput', ' welcome10 ')->call('applyCoupon')
        ->assertSet('couponCode', 'WELCOME10')->assertSee('Welcome 10%')
        ->call('place')->assertHasNoErrors();

    expect(Order::withoutTenancy()->sole())->discount_minor->toBe(500)->total_minor->toBe(4500);
});

it('uses points and a wallet reward at checkout', function () {
    [$customer, $branch, $product] = rewardsShop();
    signInRewardsGuest($customer);
    app(Points::class)->credit($customer, LedgerType::Earn, 1250, null, null);
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => '5 off', 'ar' => '5'], 'type' => RewardType::FixedOff, 'value' => 500, 'points_cost' => 100]);
    $wallet = WalletReward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'customer_id' => $customer->id, 'reward_id' => $reward->id, 'source' => 'manual', 'expires_at' => now()->addDays(5)]);

    checkoutFor($customer, $branch, $product)
        ->assertSee(__('guest.savings.use_points', ['points' => '1,200']))
        ->call('chooseReward', $wallet->id)->assertSet('walletRewardId', $wallet->id)
        ->call('togglePoints')->assertSet('usePoints', true)
        ->call('place')->assertHasNoErrors();

    // 50.00 − 5.00 reward = 45.00; the 50% cap would allow 2,200 points, the balance allows 1,200 → 12.00.
    $order = Order::withoutTenancy()->sole();
    expect($order)->discount_minor->toBe(1700)->points_redeemed->toBe(1200)
        ->and($customer->fresh()->points_balance)->toBe(50)
        ->and($wallet->fresh()->status)->toBe(WalletReward::USED);
});

it('refuses a wallet reward that belongs to someone else', function () {
    [$customer, $branch, $product] = rewardsShop();
    signInRewardsGuest($customer);
    $other = Customer::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'phone' => '+97455559999']);
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'R', 'ar' => 'R'], 'type' => RewardType::FixedOff, 'value' => 500]);
    $theirs = WalletReward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'customer_id' => $other->id, 'reward_id' => $reward->id, 'source' => 'manual']);

    checkoutFor($customer, $branch, $product)->call('chooseReward', $theirs->id)->assertSet('walletRewardId', null);
});

it('shows balance, tier progress, wallet and history, and claims a reward', function () {
    [$customer] = rewardsShop();
    signInRewardsGuest($customer);
    app(Points::class)->credit($customer, LedgerType::Earn, 400, null, null);
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Free coffee', 'ar' => 'قهوة مجانية'], 'type' => RewardType::FixedOff, 'value' => 1500, 'points_cost' => 300]);

    Livewire::test(Rewards::class, ['restaurant' => $customer->restaurant])
        ->assertSee('400')->assertSee('Free coffee')->assertSee(__('guest.rewards.wallet_empty'))
        ->assertSee(__('guest.rewards.points_added'))->assertDontSee(__('loyalty.ledger.earn'))
        ->call('claim', $reward->id)
        ->assertDispatched('toast', tone: 'success')
        ->assertDontSee(__('guest.rewards.wallet_empty'));

    expect($customer->fresh()->points_balance)->toBe(100)
        ->and(WalletReward::withoutTenancy()->sole()->status)->toBe(WalletReward::AVAILABLE);
});

it('describes every kind of reward in the catalog without extra queries', function () {
    [$customer, , $product] = rewardsShop();
    Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Treat', 'ar' => 'Treat'], 'type' => RewardType::FreeItem, 'product_id' => $product->id, 'points_cost' => 100]);
    Reward::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Pct', 'ar' => 'Pct'], 'type' => RewardType::PercentOff, 'value' => 1500, 'points_cost' => 100]);

    Livewire::test(Rewards::class, ['restaurant' => $customer->restaurant])
        ->assertSee(__('guest.rewards.gives.free_item', ['item' => 'Burger']))
        ->assertSee(__('guest.rewards.gives.percent_off', ['percent' => '15']));
});

it('asks guests who are not signed in to sign in', function () {
    [$customer] = rewardsShop();

    Livewire::test(Rewards::class, ['restaurant' => $customer->restaurant])->assertSee(__('guest.rewards.sign_in_title'));
});

it('hides the rewards page when the plan has no loyalty or the program is off', function () {
    [$customer] = rewardsShop();
    $customer->restaurant->forceFill(['feature_overrides' => ['loyalty' => false]])->save();

    $this->get(route('customer.rewards', $customer->restaurant))->assertNotFound();

    $customer->restaurant->forceFill(['feature_overrides' => ['loyalty' => true]])->save();
    $customer->restaurant->loyaltyProgram()->update(['is_enabled' => false]);
    $this->get(route('customer.rewards', $customer->restaurant))->assertNotFound();
});

it('refuses to charge a total the guest did not see, then places at the new total', function () {
    [$customer, $branch, $product] = rewardsShop();
    signInRewardsGuest($customer);
    $promo = Promotion::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'name' => ['en' => 'Lunch', 'ar' => 'غداء'], 'type' => PromotionType::Fixed, 'value_minor' => 1000]);

    $page = checkoutFor($customer, $branch, $product)->assertSee('Lunch');
    $promo->update(['archived_at' => now()]);

    $page->call('place')->assertSet('problem', __('ordering.errors.total_changed'));
    expect(Order::withoutTenancy()->count())->toBe(0);

    $page->call('place')->assertHasNoErrors();
    expect(Order::withoutTenancy()->sole()->total_minor)->toBe(5000);
});
