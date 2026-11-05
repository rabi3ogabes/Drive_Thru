<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Referrals\GuestDevice;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Referrals\Referrals;
use App\Domain\Referrals\ReferralSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\Referrals\Pages\ListReferrals;
use App\Livewire\Customer\Invite;
use App\Livewire\Customer\Rewards;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('en');
});

/** @return array{Restaurant, Customer} the restaurant with invites on, and a guest who invites */
function inviteShop(string $slug = 'masar', array $settings = []): array
{
    $restaurant = restaurant(slug: $slug, services: [Service::DriveThru, Service::Rewards]);
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => true]])->save();
    $mine = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => '50 bonus points', 'ar' => '50 نقطة'], 'type' => RewardType::BonusPoints, 'value' => 50]);
    $theirs = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Welcome 10 off', 'ar' => 'خصم 10'], 'type' => RewardType::FixedOff, 'value' => 1000]);
    ReferralSettings::save($restaurant, array_merge(['enabled' => true, 'referrer_reward_id' => $mine->id, 'friend_reward_id' => $theirs->id, 'monthly_cap' => 10], $settings));

    $referrer = Customer::withoutTenancy()->forceCreate(['restaurant_id' => $restaurant->id, 'phone' => '+97455550001', 'name' => 'Sara', 'created_at' => now()->subMonth()]);

    return [$restaurant->fresh(), $referrer];
}

/** The friend opens the link and signs in on the invite page. */
function joinThrough(Restaurant $restaurant, Customer $referrer, string $phone = '55559999', ?string $device = null)
{
    $code = app(Referrals::class)->codeFor($referrer);
    $page = ($device === null ? Livewire::test(Invite::class, ['restaurant' => $restaurant, 'code' => $code])
        : Livewire::withCookies([GuestDevice::COOKIE => $device])->test(Invite::class, ['restaurant' => $restaurant, 'code' => $code]));

    $page->set('phone', $phone)->call('sendCode');

    return $page->set('code', $page->get('demoCode'))->call('verifyCode');
}

function friendPicksUp(Customer $friend, array $attributes = []): Order
{
    $order = pastOrder($friend, 4000, now(), attributes: $attributes);
    event(new OrderStatusChanged($order, OrderStatus::Ready, OrderStatus::PickedUp));

    return $order;
}

function friendOf(Restaurant $restaurant, string $phone = '+97455559999'): Customer
{
    return Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('phone', $phone)->sole();
}

it('rewards both guests once, after the friend’s first picked-up order', function () {
    [$restaurant, $referrer] = inviteShop();

    $page = $this->get(route('customer.invite', [$restaurant, app(Referrals::class)->codeFor($referrer)]))
        ->assertOk()->assertSee('Sara invited you to Masar Grill')->assertSee('Welcome 10 off');

    // The browser gets its device cookie for the whole site, kept for years.
    expect($page->getCookie(GuestDevice::COOKIE, false))
        ->getPath()->toBe('/')
        ->isHttpOnly()->toBeTrue()
        ->getExpiresTime()->toBeGreaterThan(now()->addYears(4)->getTimestamp());

    joinThrough($restaurant, $referrer)->assertSet('state', 'joined')->assertSee(__('referrals.invite.joined_title'));
    $friend = friendOf($restaurant);

    expect(Referral::withoutTenancy()->sole())->status->toBe(Referral::PENDING)->referrer_id->toBe($referrer->id)
        ->and(WalletReward::withoutTenancy()->count())->toBe(0);

    $order = friendPicksUp($friend);
    event(new OrderStatusChanged($order, OrderStatus::Ready, OrderStatus::PickedUp));
    friendPicksUp($friend);

    expect(Referral::withoutTenancy()->sole())->status->toBe(Referral::REWARDED)->order_id->toBe($order->id)
        ->and($referrer->fresh()->points_balance)->toBe(50)
        ->and(WalletReward::withoutTenancy()->where('source', 'referral')->count())->toBe(2)
        ->and(WalletReward::withoutTenancy()->where('customer_id', $friend->id)->sole()->status)->toBe(WalletReward::AVAILABLE);
});

it('rewards nobody for a cancelled first order, and waits for the next one', function () {
    [$restaurant, $referrer] = inviteShop();
    joinThrough($restaurant, $referrer);
    $friend = friendOf($restaurant);

    $cancelled = pastOrder($friend, 4000, now(), status: 'cancelled');
    event(new OrderStatusChanged($cancelled, OrderStatus::Received, OrderStatus::Cancelled));

    expect(Referral::withoutTenancy()->sole()->status)->toBe(Referral::PENDING)
        ->and(WalletReward::withoutTenancy()->count())->toBe(0);

    friendPicksUp($friend);
    expect(Referral::withoutTenancy()->sole()->status)->toBe(Referral::REWARDED);
});

it('remembers the invite when the friend signs in later, somewhere else', function () {
    [$restaurant, $referrer] = inviteShop();
    $code = app(Referrals::class)->codeFor($referrer);

    $this->get(route('customer.invite', [$restaurant, strtolower($code)]))->assertOk();
    $this->travel(2)->days();

    $page = Livewire::test(Rewards::class, ['restaurant' => $restaurant])->set('phone', '55559999')->call('sendCode');
    $page->set('code', $page->get('demoCode'))->call('verifyCode');

    expect(Referral::withoutTenancy()->sole())->friend_id->toBe(friendOf($restaurant)->id)->status->toBe(Referral::PENDING);
});

it('rejects a referral from the inviter’s own phone', function () {
    [$restaurant, $referrer] = inviteShop();
    $device = Str::random(40);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $referrer->id, 'token_hash' => hash('sha256', 'x'), 'device_hash' => hash_hmac('sha256', $device, (string) config('app.key'))]);

    joinThrough($restaurant, $referrer, device: $device)->assertSet('state', 'joined');
    friendPicksUp(friendOf($restaurant));

    expect(Referral::withoutTenancy()->sole())->status->toBe(Referral::REJECTED)->reason->toBe('same_device')
        ->and(WalletReward::withoutTenancy()->count())->toBe(0);
});

it('rejects a referral picked up in the inviter’s car, however the plate is typed', function () {
    [$restaurant, $referrer] = inviteShop();
    CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $referrer->id, 'plate' => '123456']);

    joinThrough($restaurant, $referrer);
    friendPicksUp(friendOf($restaurant), ['vehicle' => ['plate' => '١٢٣ ٤٥٦', 'label' => 'White car']]);

    expect(Referral::withoutTenancy()->sole())->status->toBe(Referral::REJECTED)->reason->toBe('same_car');
});

it('rejects a guest who was already a guest, and the inviter’s own link', function () {
    [$restaurant, $referrer] = inviteShop();
    Customer::withoutTenancy()->forceCreate(['restaurant_id' => $restaurant->id, 'phone' => '+97455559999', 'created_at' => now()->subWeek()]);

    joinThrough($restaurant, $referrer)->assertSet('state', 'member');
    expect(Referral::withoutTenancy()->sole())->status->toBe(Referral::REJECTED)->reason->toBe('existing_guest');

    session()->flush();
    app()->forgetScopedInstances();
    joinThrough($restaurant, $referrer, '55550001')->assertSet('state', 'own');

    expect(Referral::withoutTenancy()->where('friend_id', $referrer->id)->sole()->reason)->toBe('self');
});

it('stops rewarding an inviter past the monthly cap', function () {
    [$restaurant, $referrer] = inviteShop(settings: ['monthly_cap' => 1]);
    $earlier = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455558888']);
    Referral::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'referrer_id' => $referrer->id, 'friend_id' => $earlier->id, 'status' => Referral::REWARDED, 'joined_at' => now(), 'decided_at' => now()]);

    joinThrough($restaurant, $referrer);
    friendPicksUp(friendOf($restaurant));

    expect(Referral::withoutTenancy()->where('friend_id', '!=', $earlier->id)->sole())->status->toBe(Referral::REJECTED)->reason->toBe('monthly_cap');
});

it('keeps invite codes to their restaurant, and shows nothing while invites are off', function () {
    [$other, $theirs] = inviteShop('elsewhere');
    [$restaurant] = inviteShop();

    Livewire::test(Invite::class, ['restaurant' => $restaurant, 'code' => app(Referrals::class)->codeFor($theirs)])
        ->assertSet('state', 'invalid')->assertSee(__('referrals.invite.invalid_title'));

    ReferralSettings::save($other, ['enabled' => false]);
    Livewire::test(Invite::class, ['restaurant' => $other->fresh(), 'code' => app(Referrals::class)->codeFor($theirs)])->assertSet('state', 'invalid');
});

it('gives each guest a link to share and counts the friends who joined', function () {
    [$restaurant, $referrer] = inviteShop();
    joinThrough($restaurant, $referrer);
    session()->flush();
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $referrer->id, 'token_hash' => hash('sha256', $token)]);
    session()->put("customer.{$restaurant->id}", $token);
    app()->forgetScopedInstances();

    Livewire::test(Rewards::class, ['restaurant' => $restaurant])
        ->assertSee(__('referrals.card.title'))
        ->assertSee('/masar/invite/'.$referrer->fresh()->referral_code)
        ->assertSee('1 friend joined');
});

it('shows the owner every referral with its reason, and lets them change the settings', function () {
    [$restaurant, $referrer] = inviteShop();
    CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $referrer->id, 'plate' => '777']);
    joinThrough($restaurant, $referrer);
    friendPicksUp(friendOf($restaurant), ['vehicle' => ['plate' => '777']]);

    $this->actingAs(ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    Livewire::test(ListReferrals::class)
        ->assertSee(__('referrals.reasons.same_car'))
        ->mountAction('settings')->fillForm(['monthly_cap' => 3])->callMountedAction()->assertHasNoFormErrors();

    expect(ReferralSettings::for($restaurant->fresh())->monthlyCap)->toBe(3);
});

it('keeps the referrals list to staff who manage campaigns', function () {
    [$restaurant] = inviteShop();
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->actingAs(employeeAt($restaurant, 'cashier', ['all_branches' => true]))
        ->get(ListReferrals::getUrl(tenant: $restaurant))->assertForbidden();
});
