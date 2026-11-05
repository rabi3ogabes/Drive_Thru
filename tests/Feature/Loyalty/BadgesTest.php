<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Loyalty\Badges\Badge;
use App\Domain\Loyalty\Badges\BadgeProgress;
use App\Domain\Loyalty\Badges\BadgeSettings;
use App\Domain\Loyalty\Badges\CustomerBadge;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\LoyaltySettings;
use App\Filament\App\Resources\Customers\Pages\ViewGuest;
use App\Livewire\Customer\Rewards;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app()->setLocale('en');
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Qatar'));
});

/** @return array{Restaurant, Customer} */
function badgeShop(string $slug = 'masar', bool $on = true, array $settings = []): array
{
    $restaurant = restaurant(slug: $slug, services: [Service::DriveThru, Service::Rewards]);
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => true], 'timezone' => 'Asia/Qatar'])->save();

    if ($on) {
        BadgeSettings::save($restaurant, array_merge(['enabled' => true], $settings));
    }

    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+9745555'.random_int(1000, 9999), 'name' => 'Sara', 'locale' => 'en']);

    return [$restaurant->fresh(), $guest];
}

/** A picked-up order announced to the listeners, with one line per product. */
function pickedUp(Customer $guest, ?CarbonImmutable $at = null, array $productIds = []): Order
{
    // Stored as UTC, like the app does.
    $order = pastOrder($guest, 2000, ($at ?? now()->toImmutable())->utc(), productIds: $productIds);
    event(new OrderStatusChanged($order, OrderStatus::Ready, OrderStatus::PickedUp));

    return $order;
}

function badgesOf(Customer $guest): array
{
    return CustomerBadge::withoutTenancy()->where('customer_id', $guest->id)->orderBy('id')->pluck('badge')->map(fn ($b) => $b instanceof Badge ? $b->value : $b)->all();
}

it('records nothing while badges are off (the default)', function () {
    [$restaurant, $guest] = badgeShop(on: false);

    pickedUp($guest);

    expect(BadgeSettings::for($restaurant)->enabled)->toBeFalse()
        ->and(CustomerBadge::withoutTenancy()->count())->toBe(0);
});

it('earns first order, 10 and 25 orders, explorer and early bird by their rules', function () {
    [$restaurant, $guest] = badgeShop();
    $products = Product::factory()->count(5)->create(['restaurant_id' => $restaurant->id]);

    pickedUp($guest, CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Qatar'), [$products[0]->id]);
    expect(badgesOf($guest))->toBe(['first_order']);

    // Four more early orders (08:30 Doha), the fifth early one makes the early bird.
    foreach (range(1, 4) as $i) {
        pickedUp($guest, CarbonImmutable::parse('2026-11-04 08:30', 'Asia/Qatar')->subDays($i), [$products[$i]->id]);
    }
    expect(badgesOf($guest))->toBe(['first_order', 'explorer']);

    pickedUp($guest, CarbonImmutable::parse('2026-10-20 08:59', 'Asia/Qatar'));
    expect(badgesOf($guest))->toContain('early_bird')->not->toContain('orders_10');

    foreach (range(1, 4) as $i) {
        pickedUp($guest, now()->toImmutable()->subDays(20 + $i));
    }
    expect(badgesOf($guest))->toContain('orders_10')->not->toContain('orders_25');

    $progress = BadgeProgress::for($guest, BadgeSettings::for($restaurant), now()->toImmutable());
    expect($progress['orders_25'])->toMatchArray(['current' => 10, 'target' => 25, 'earned' => null])
        ->and($progress['early_bird']['current'])->toBe(5);
});

it('counts a weekly streak across the new year and ignores cancelled orders', function () {
    [$restaurant, $guest] = badgeShop();
    $this->travelTo(CarbonImmutable::parse('2027-01-03 12:00', 'Asia/Qatar'));

    pastOrder($guest, 2000, CarbonImmutable::parse('2026-12-21 13:00', 'Asia/Qatar')->utc());
    pastOrder($guest, 2000, CarbonImmutable::parse('2026-12-14 13:00', 'Asia/Qatar')->utc(), status: 'cancelled');
    pastOrder($guest, 2000, CarbonImmutable::parse('2026-12-29 13:00', 'Asia/Qatar')->utc());
    pickedUp($guest, CarbonImmutable::parse('2027-01-03 11:00', 'Asia/Qatar'));

    $streak = BadgeProgress::streak($guest, 'Asia/Qatar', now()->toImmutable());
    expect($streak['weeks'])->toBe(3)
        ->and($streak['keepBy']->setTimezone('Asia/Qatar')->format('Y-m-d'))->toBe('2027-01-16')
        ->and(badgesOf($guest))->toContain('streak');
});

it('grants a badge reward once, even if the event fires twice', function () {
    [$restaurant, $guest] = badgeShop();
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Welcome treat', 'ar' => 'هدية'], 'type' => RewardType::FixedOff, 'value' => 500]);
    BadgeSettings::save($restaurant, ['enabled' => true, 'badges' => ['first_order' => ['on' => true, 'reward_id' => $reward->id]]]);

    $order = pickedUp($guest);
    event(new OrderStatusChanged($order, OrderStatus::Ready, OrderStatus::PickedUp));

    expect(CustomerBadge::withoutTenancy()->where('badge', 'first_order')->count())->toBe(1)
        ->and(WalletReward::withoutTenancy()->where('source', 'badge')->count())->toBe(1)
        ->and(CustomerBadge::withoutTenancy()->sole()->wallet_reward_id)->not->toBeNull();
});

it('skips a badge the owner switched off', function () {
    [$restaurant, $guest] = badgeShop(settings: ['badges' => ['first_order' => ['on' => false]]]);

    pickedUp($guest);

    expect(badgesOf($guest))->toBe([]);
});

it('shows achievements with progress and celebrates a new badge once', function () {
    [$restaurant, $guest] = badgeShop();
    pickedUp($guest);
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$restaurant->id}", $token);
    app()->forgetScopedInstances();

    Livewire::test(Rewards::class, ['restaurant' => $restaurant])
        ->assertSee(__('loyalty.badges.achievements'))
        ->assertSee(__('loyalty.badges.names.first_order'))
        ->assertSee(__('loyalty.badges.progress', ['current' => 1, 'target' => 10]))
        ->assertSee(__('loyalty.badges.unlocked'));

    expect(CustomerBadge::withoutTenancy()->sole()->seen_at)->not->toBeNull();

    app()->forgetScopedInstances();
    Livewire::test(Rewards::class, ['restaurant' => $restaurant])->assertDontSee(__('loyalty.badges.unlocked'));
});

it('awards badges guests already reached when the owner switches badges on later', function () {
    [$restaurant, $guest] = badgeShop(on: false);
    pickedUp($guest);
    expect(badgesOf($guest))->toBe([]);

    BadgeSettings::save($restaurant, ['enabled' => true]);
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$restaurant->id}", $token);
    app()->forgetScopedInstances();

    Livewire::test(Rewards::class, ['restaurant' => $restaurant->fresh()])
        ->assertSee(__('loyalty.badges.unlocked'))
        ->assertSee(__('loyalty.badges.earned_on', ['date' => now()->setTimezone('Asia/Qatar')->format('d/m/Y')]));

    expect(badgesOf($guest))->toBe(['first_order']);
});

it('saves badge settings, shows who holds each badge, and keeps badges to their restaurant', function () {
    [$other, $theirGuest] = badgeShop('elsewhere');
    pickedUp($theirGuest);
    [$restaurant, $guest] = badgeShop(on: false);

    $this->actingAs(ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    Livewire::test(LoyaltySettings::class)
        ->fillForm(['badges' => ['enabled' => true, 'streak_weeks' => 4, 'badges' => ['explorer' => ['on' => false, 'reward_id' => null]]]])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = BadgeSettings::for($restaurant->fresh());
    expect($settings)->enabled->toBeTrue()->streakWeeks->toBe(4)
        ->and($settings->isOn(Badge::Explorer))->toBeFalse()
        ->and($settings->isOn(Badge::FirstOrder))->toBeTrue()
        ->and(Activity::query()->where('event', 'loyalty.badge_settings_updated')->count())->toBe(1);

    pickedUp($guest);
    expect(badgesOf($guest))->toBe(['first_order'])
        ->and(CustomerBadge::withoutTenancy()->where('restaurant_id', $restaurant->id)->count())->toBe(1);

    Livewire::test(LoyaltySettings::class)->assertSee(trans_choice('loyalty.badges.holders', 1, ['count' => 1]));
    Livewire::test(ViewGuest::class, ['record' => $guest->id])->assertSee(__('loyalty.badges.names.first_order'));
});
