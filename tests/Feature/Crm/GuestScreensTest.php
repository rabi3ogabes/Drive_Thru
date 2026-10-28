<?php

use App\Domain\Crm\Consent;
use App\Domain\Crm\CrmSettings;
use App\Domain\Crm\Enums\Segment;
use App\Domain\Crm\GuestExport;
use App\Domain\Crm\GuestStats;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\GuestSettings;
use App\Filament\App\Resources\Customers\Pages\ListGuests;
use App\Filament\App\Resources\Customers\Pages\ViewGuest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** @return array{Restaurant, User} */
function guestWorkspace(?string $position = null): array
{
    $restaurant = restaurant(slug: 'masar');
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => true]])->save();
    $user = $position === null ? ownerOf($restaurant) : employeeAt($restaurant, $position, ['all_branches' => true]);
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    return [$restaurant->fresh(), $user];
}

function listedGuest(Restaurant $restaurant, string $phone, ?string $name = 'Noura Al-Thani'): Customer
{
    return Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => $phone, 'name' => $name]);
}

it('refuses the guest screens to a cashier', function () {
    [$restaurant] = guestWorkspace('cashier');
    $guest = listedGuest($restaurant, '+97455551234');

    $this->get(ListGuests::getUrl(tenant: $restaurant))->assertForbidden();
    $this->get(ViewGuest::getUrl(['record' => $guest], tenant: $restaurant))->assertForbidden();
    $this->get(GuestSettings::getUrl(tenant: $restaurant))->assertForbidden();
});

it('lists guests with their segments, and masks phones without the contacts permission', function () {
    [$restaurant] = guestWorkspace('branch_manager');
    $guest = listedGuest($restaurant, '+97455551234');
    pastOrder($guest, 4500, now()->subDays(3));
    CustomerSegment::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'segment' => Segment::New, 'computed_at' => now()]);

    $this->get(ListGuests::getUrl(tenant: $restaurant))
        ->assertOk()
        ->assertSee('Noura Al-Thani')
        ->assertSee('+974 •••• 1234')
        ->assertDontSee('55551234')
        ->assertSee(Segment::New->getLabel());

    $this->get(ViewGuest::getUrl(['record' => $guest], tenant: $restaurant))
        ->assertOk()
        ->assertSee('+974 •••• 1234')
        ->assertDontSee('55551234');
});

it('shows the full phone to marketing and finds guests by number', function () {
    [$restaurant] = guestWorkspace('marketing');
    $noura = listedGuest($restaurant, '+97455551234');
    $other = listedGuest($restaurant, '+97466660000', 'Hamad');

    $this->get(ListGuests::getUrl(tenant: $restaurant))->assertSee('+97455551234', false);

    Livewire::test(ListGuests::class)
        ->searchTable('5555')
        ->assertCanSeeTableRecords([$noura])
        ->assertCanNotSeeTableRecords([$other]);
});

it('does not search by phone for staff who cannot see contacts', function () {
    [$restaurant] = guestWorkspace('branch_manager');
    $noura = listedGuest($restaurant, '+97455551234');

    Livewire::test(ListGuests::class)
        ->searchTable('55551234')
        ->assertCanNotSeeTableRecords([$noura]);
});

it('filters guests by segment and by consent', function () {
    [$restaurant] = guestWorkspace();
    $vip = listedGuest($restaurant, '+97455550001', 'Vip');
    $plain = listedGuest($restaurant, '+97455550002', 'Plain');
    CustomerSegment::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $vip->id, 'segment' => Segment::Vip, 'computed_at' => now()]);
    app(Consent::class)->give($plain, 'checkout');

    Livewire::test(ListGuests::class)
        ->filterTable('segment', Segment::Vip->value)
        ->assertCanSeeTableRecords([$vip])
        ->assertCanNotSeeTableRecords([$plain]);

    Livewire::test(ListGuests::class)
        ->filterTable('offers', true)
        ->assertCanSeeTableRecords([$plain])
        ->assertCanNotSeeTableRecords([$vip]);
});

it('never shows another restaurant’s guests', function () {
    [$restaurant] = guestWorkspace();
    $mine = listedGuest($restaurant, '+97455550001', 'Mine');
    $elsewhere = restaurant(['en' => 'Other', 'ar' => 'آخر'], slug: 'other');
    $theirs = listedGuest($elsewhere, '+97455550009', 'Theirs');

    Livewire::test(ListGuests::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);

    $this->get(ViewGuest::getUrl(['record' => $theirs->id], tenant: $restaurant))->assertNotFound();
});

it('renders the profile in a fixed number of queries', function () {
    [$restaurant] = guestWorkspace();
    $guest = listedGuest($restaurant, '+97455551234');
    $count = function () use ($guest, $restaurant): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(ViewGuest::getUrl(['record' => $guest], tenant: $restaurant))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $products = Product::factory()->count(3)->create(['restaurant_id' => $restaurant->id]);
    pastOrder($guest, 3000, now()->subDays(2), productIds: [$products[0]->id]);
    $few = $count();

    foreach (range(1, 8) as $i) {
        pastOrder($guest, 3000, now()->subDays($i + 2), productIds: $products->pluck('id')->all());
    }
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Free karak', 'ar' => 'كرك مجاني'], 'type' => RewardType::FixedOff, 'value' => 500, 'points_cost' => 100, 'valid_days' => 30]);
    foreach (range(1, 3) as $i) {
        WalletReward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'reward_id' => $reward->id, 'source' => 'manual', 'status' => WalletReward::AVAILABLE, 'expires_at' => now()->addDays(10)]);
    }
    foreach (Segment::cases() as $segment) {
        CustomerSegment::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'segment' => $segment, 'computed_at' => now()]);
    }

    expect($count())->toBeLessThanOrEqual($few + 2);
});

it('adjusts points, gives a reward and stops offers from the profile', function () {
    [$restaurant, $owner] = guestWorkspace();
    $guest = listedGuest($restaurant, '+97455551234');
    app(Consent::class)->give($guest, 'checkout');
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Free karak', 'ar' => 'كرك مجاني'], 'type' => RewardType::FixedOff, 'value' => 500, 'points_cost' => 100, 'valid_days' => 30]);

    Livewire::test(ViewGuest::class, ['record' => $guest->id])
        ->callAction('adjustPoints', data: ['points' => 250, 'reason' => 'Cold coffee, sorry'])
        ->assertHasNoActionErrors()
        ->callAction('grantReward', data: ['reward_id' => $reward->id])
        ->assertHasNoActionErrors()
        ->callAction('withdrawConsent')
        ->assertNotified();

    $guest->refresh();
    expect($guest->points_balance)->toBe(250)
        ->and(LedgerEntry::query()->where('customer_id', $guest->id)->sole()->note)->toBe('Cold coffee, sorry')
        ->and(WalletReward::query()->where('customer_id', $guest->id)->sole()->source)->toBe('manual')
        ->and($guest->acceptsOffers())->toBeFalse()
        ->and(Activity::query()->where('event', 'crm.consent_withdrawn')->sole()->causer_id)->toBe($owner->id);
});

it('does not let staff without loyalty rights change points', function () {
    [$restaurant] = guestWorkspace('branch_manager');
    $guest = listedGuest($restaurant, '+97455551234');

    Livewire::test(ViewGuest::class, ['record' => $guest->id])
        ->assertActionHidden('adjustPoints')
        ->assertActionHidden('grantReward');
});

it('exports the filtered list, masking phones without the contacts permission', function () {
    [$restaurant, $user] = guestWorkspace('branch_manager');
    $guest = listedGuest($restaurant, '+97455551234', '=HYPERLINK("x")');
    pastOrder($guest, 4500, now()->subDays(3));
    app(GuestStats::class)->refresh($guest);

    $response = app(GuestExport::class)->download(Customer::query(), $restaurant, $user, withPhones: false);
    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toStartWith("\u{FEFF}")
        ->toContain('+974 •••• 1234')
        ->not->toContain('+97455551234')
        ->toContain("'=HYPERLINK")
        ->toContain('45.00')
        ->and(Activity::query()->where('event', 'crm.guests_exported')->sole()->causer_id)->toBe($user->id);

    Livewire::test(ListGuests::class)->assertActionVisible('export');
});

it('hides the export without the export permission', function () {
    guestWorkspace('marketing');

    Livewire::test(ListGuests::class)->assertActionHidden('export');
});

it('saves guest rules and checks that inactive comes after at risk', function () {
    [$restaurant] = guestWorkspace();

    Livewire::test(GuestSettings::class)
        ->fillForm(['at_risk_days' => 60, 'inactive_days' => 30])
        ->call('save')
        ->assertHasFormErrors(['inactive_days' => 'gt']);

    Livewire::test(GuestSettings::class)
        ->fillForm(['new_days' => 14, 'at_risk_days' => 45, 'inactive_days' => 90, 'quiet_start' => '23:00', 'quiet_end' => '08:30'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = CrmSettings::for($restaurant->fresh());
    expect($settings->int('new_days'))->toBe(14)
        ->and($settings->int('inactive_days'))->toBe(90)
        ->and($settings->time('quiet_start'))->toBe('23:00')
        ->and($settings->time('quiet_end'))->toBe('08:30');
});
