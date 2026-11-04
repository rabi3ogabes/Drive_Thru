<?php

use App\Domain\Crm\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Loyalty\Birthdays\BirthdayGift;
use App\Domain\Loyalty\Birthdays\BirthdaySettings;
use App\Domain\Loyalty\Birthdays\SendBirthdayGifts;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Notifications\Push\PushTransport;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\LoyaltySettings;
use App\Livewire\Customer\Rewards;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** Records pushes instead of sending them. */
final class BirthdayPushTransport implements PushTransport
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function configured(): bool
    {
        return true;
    }

    public function send(PushSubscription $subscription, array $payload): string
    {
        $this->sent[] = $payload;

        return self::SENT;
    }
}

beforeEach(function () {
    app()->setLocale('en');
    config(['messaging.log_when_unconfigured' => true]);
    // 23:30 UTC on 4 Nov is already 5 Nov in Doha.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 23:30', 'UTC'));
});

/** @return array{Restaurant, Reward} a restaurant with the birthday gift on (3 days before) */
function birthdayShop(string $slug = 'masar', array $settings = []): array
{
    $restaurant = restaurant(slug: $slug, services: [Service::DriveThru, Service::Rewards]);
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => true], 'timezone' => 'Asia/Qatar'])->save();
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Birthday cake', 'ar' => 'كعكة عيد الميلاد'], 'type' => RewardType::FixedOff, 'value' => 1500, 'valid_days' => 14]);
    BirthdaySettings::save($restaurant, array_merge(['enabled' => true, 'reward_id' => $reward->id, 'days_before' => 3, 'send_message' => true], $settings));

    return [$restaurant->fresh(), $reward];
}

function birthdayGuest(Restaurant $restaurant, ?string $birthday, string $phone = '+97455551111'): Customer
{
    return Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => $phone, 'name' => 'Sara', 'birthday' => $birthday, 'locale' => 'en']);
}

it('gives the gift 3 days before the birthday in the restaurant time zone', function () {
    [$restaurant] = birthdayShop();
    $due = birthdayGuest($restaurant, '1994-11-08');          // 5 Nov Doha + 3 days
    birthdayGuest($restaurant, '1994-11-07', '+97455552222'); // that was yesterday's run
    birthdayGuest($restaurant, null, '+97455553333');

    expect(app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable()))->toBe(1)
        ->and(BirthdayGift::withoutTenancy()->sole())->customer_id->toBe($due->id)->year->toBe(2026)
        ->and(WalletReward::withoutTenancy()->sole())->source->toBe('birthday')->customer_id->toBe($due->id);
});

it('treats 29 February as 28 February in other years', function () {
    [$restaurant] = birthdayShop(settings: ['days_before' => 0]);
    $leap = birthdayGuest($restaurant, '2000-02-29');

    $this->travelTo(CarbonImmutable::parse('2027-02-28 08:00', 'Asia/Qatar'));
    expect(app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable()))->toBe(1)
        ->and(BirthdayGift::withoutTenancy()->sole()->customer_id)->toBe($leap->id);
});

it('gives one gift per 365 days even if the guest changes the birthday', function () {
    [$restaurant] = birthdayShop();
    $guest = birthdayGuest($restaurant, '1994-11-08');
    app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable());

    $guest->forceFill(['birthday' => '1994-12-10'])->save();
    $this->travelTo(CarbonImmutable::parse('2026-12-07 08:00', 'Asia/Qatar'));

    expect(app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable()))->toBe(0)
        ->and(BirthdayGift::withoutTenancy()->count())->toBe(1);
});

it('does nothing on a second run the same day', function () {
    [$restaurant] = birthdayShop();
    birthdayGuest($restaurant, '1994-11-08');

    app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable());
    expect(app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable()))->toBe(0)
        ->and(WalletReward::withoutTenancy()->count())->toBe(1);
});

it('messages only guests who accepted offers, and pushes to subscribers', function () {
    [$restaurant] = birthdayShop();
    $push = new BirthdayPushTransport;
    app()->instance(PushTransport::class, $push);
    $agreed = birthdayGuest($restaurant, '1994-11-08');
    app(Consent::class)->give($agreed, 'account');
    $quiet = birthdayGuest($restaurant, '1990-11-08', '+97455552222');
    PushSubscription::query()->create(['subscriber_type' => $quiet->getMorphClass(), 'subscriber_id' => $quiet->id, 'endpoint' => 'https://push.example.test/1', 'public_key' => 'BKey', 'auth_token' => 'auth', 'locale' => 'en']);

    app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable());

    expect(MessageDelivery::withoutTenancy()->count())->toBe(1)
        ->and(MessageDelivery::withoutTenancy()->sole()->to_hash)->toBe(hash('sha256', $agreed->phone))
        ->and($push->sent)->toHaveCount(1)
        ->and($push->sent[0]['title'])->toBe(__('loyalty.birthday.push_title'));
});

it('skips quietly when the reward was archived or the gift is off', function () {
    [$restaurant, $reward] = birthdayShop();
    birthdayGuest($restaurant, '1994-11-08');

    $reward->forceFill(['archived_at' => now(), 'is_active' => false])->save();
    expect(app(SendBirthdayGifts::class)->run($restaurant->fresh(), now()->toImmutable()))->toBe(0);

    $reward->forceFill(['archived_at' => null, 'is_active' => true])->save();
    BirthdaySettings::save($restaurant, ['enabled' => false, 'reward_id' => $reward->id]);
    expect(app(SendBirthdayGifts::class)->run($restaurant->fresh(), now()->toImmutable()))->toBe(0)
        ->and(BirthdayGift::withoutTenancy()->count())->toBe(0);

    $this->artisan('loyalty:birthdays')->assertSuccessful();
});

it('asks for the birthday on the Rewards page when the gift is on and shows a birthday card that week', function () {
    [$restaurant] = birthdayShop();
    $guest = birthdayGuest($restaurant, null);
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$restaurant->id}", $token);
    app()->forgetScopedInstances();

    Livewire::test(Rewards::class, ['restaurant' => $restaurant])
        ->assertSee(__('loyalty.birthday.ask_title'))
        ->set('birthday', '1994-11-08')->call('saveBirthday')
        ->assertHasNoErrors()
        ->assertDontSee(__('loyalty.birthday.ask_title'));

    expect($guest->fresh()->birthday->format('Y-m-d'))->toBe('1994-11-08');

    app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable());
    app()->forgetScopedInstances();
    Livewire::test(Rewards::class, ['restaurant' => $restaurant])->assertSee(__('loyalty.birthday.card_title', ['name' => 'Sara']));
});

it('saves the settings from the Loyalty page, shows what is coming, and audits it', function () {
    [$restaurant, $reward] = birthdayShop(settings: ['enabled' => false]);
    birthdayGuest($restaurant, '1994-11-09');
    $this->actingAs(ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    Livewire::test(LoyaltySettings::class)
        ->assertSee(trans_choice('loyalty.birthday.upcoming', 1, ['count' => 1]))
        ->fillForm(['birthday' => ['enabled' => true, 'reward_id' => $reward->id, 'days_before' => 5, 'send_message' => false]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(BirthdaySettings::for($restaurant->fresh()))->enabled->toBeTrue()->daysBefore->toBe(5)->sendMessage->toBeFalse()
        ->and(Activity::query()->where('event', 'loyalty.birthday_settings_updated')->count())->toBe(1);
});

it('keeps gifts to their restaurant', function () {
    [$other] = birthdayShop('elsewhere');
    birthdayGuest($other, '1994-11-08', '+97455559999');
    [$restaurant] = birthdayShop();

    expect(app(SendBirthdayGifts::class)->run($restaurant, now()->toImmutable()))->toBe(0)
        ->and(app(SendBirthdayGifts::class)->run($other, now()->toImmutable()))->toBe(1)
        ->and(BirthdayGift::withoutTenancy()->sole()->restaurant_id)->toBe($other->id);
});
