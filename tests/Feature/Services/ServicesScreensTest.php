<?php

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\ServicePrice;
use App\Filament\Admin\Resources\Plans\Pages\ListPlans;
use App\Filament\Admin\Resources\Restaurants\Pages\ViewRestaurant;
use App\Filament\App\Pages\Billing;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    app()->setLocale('en');
});

function servicesClient(): array
{
    $restaurant = restaurant(slug: 'masar', services: []);
    $owner = ownerOf($restaurant);
    $plan = Plan::factory()->create(['price_minor' => 0]);
    Subscription::withoutTenancy()->create([
        'restaurant_id' => $restaurant->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active,
        'quantity' => 1, 'unit_price_minor' => 0, 'currency' => 'QAR', 'interval' => 'month',
        'current_period_start' => now()->startOfDay(), 'current_period_end' => now()->startOfDay()->addMonth(),
    ]);

    return [$restaurant->fresh(), $owner];
}

function onBillingAs(User $user, $restaurant)
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);

    return Livewire::test(Billing::class);
}

it('shows the owner every service with its price and a free demo', function () {
    [$restaurant, $owner] = servicesClient();

    onBillingAs($owner, $restaurant)
        ->assertOk()
        ->assertSee(['Drive-Thru', 'Order-Ready Call', 'Rewards'])
        ->assertSee(__('services.free_demo', ['days' => 14]))
        ->assertSee(__('services.owner.nothing'));
});

it('lets the owner try, keep, cancel and add services from Billing', function () {
    [$restaurant, $owner] = servicesClient();
    $page = onBillingAs($owner, $restaurant);

    $page->callAction(TestAction::make('tryService')->arguments(['service' => 'rewards']))->assertNotified();
    expect($restaurant->fresh()->service(Service::Rewards)->status)->toBe(ServiceStatus::Demo);

    $page->callAction(TestAction::make('keepService')->arguments(['service' => 'rewards']));
    expect($restaurant->fresh()->service(Service::Rewards)->keep_after_demo)->toBeTrue();

    $page->callAction(TestAction::make('addService')->arguments(['service' => 'drive_thru']));
    expect($restaurant->fresh()->service(Service::DriveThru)->status)->toBe(ServiceStatus::Active)
        ->and(Invoice::withoutTenancy()->count())->toBe(1);

    $page->callAction(TestAction::make('cancelService')->arguments(['service' => 'drive_thru']));
    expect($restaurant->fresh()->service(Service::DriveThru)->status)->toBe(ServiceStatus::Cancelling);

    $page->callAction(TestAction::make('resumeService')->arguments(['service' => 'drive_thru']));
    expect($restaurant->fresh()->service(Service::DriveThru)->status)->toBe(ServiceStatus::Active);

    // A second demo of the same service is refused politely.
    $page->callAction(TestAction::make('cancelService')->arguments(['service' => 'rewards']));
    $page->callAction(TestAction::make('tryService')->arguments(['service' => 'rewards']))
        ->assertNotified(__('services.errors.demo_used', ['service' => 'Rewards']));
});

it('keeps Billing away from staff without the billing permission', function () {
    [$restaurant] = servicesClient();
    $manager = employeeAt($restaurant, 'manager');

    $this->actingAs($manager)->get('/app/masar/billing')->assertForbidden();
});

it('lets the platform add a service as a demo, activate it at a client price, and end it', function () {
    [$restaurant, $owner] = servicesClient();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $page = Livewire::test(ViewRestaurant::class, ['record' => $restaurant->slug])
        ->assertSee(__('services.admin.title'));

    $page->callAction(TestAction::make('serviceDemo_ready_call')->schemaComponent('service_actions_ready_call', schema: 'infolist'), data: ['days' => 21]);
    expect($restaurant->fresh()->service(Service::ReadyCall))
        ->status->toBe(ServiceStatus::Demo)
        ->and($restaurant->fresh()->service(Service::ReadyCall)->demo_ends_at->toDateString())->toBe(now()->addDays(21)->toDateString());

    $page->callAction(TestAction::make('serviceActivate_ready_call')->schemaComponent('service_actions_ready_call', schema: 'infolist'), data: ['price' => '99.00', 'reason' => 'Launch partner']);
    expect($restaurant->fresh()->service(Service::ReadyCall))
        ->status->toBe(ServiceStatus::Active)
        ->unit_price_minor->toBe(9900);

    $page->callAction(TestAction::make('serviceEnd_ready_call')->schemaComponent('service_actions_ready_call', schema: 'infolist'), data: ['reason' => 'Closed the counter']);
    expect($restaurant->fresh()->hasService(Service::ReadyCall))->toBeFalse()
        ->and(Activity::query()->where('event', 'services.ended')->sole()->causer_id)->toBe($admin->id);
});

it('hides the service actions from platform staff without the billing permission', function () {
    [$restaurant] = servicesClient();
    $viewer = User::factory()->create();
    $viewer->forceFill(['platform_role_id' => PlatformRole::factory()->granting([PlatformPermission::ViewClients])->create()->id])->save();
    $this->actingAs($viewer);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ViewRestaurant::class, ['record' => $restaurant->slug])
        ->assertSee(__('services.admin.title'))
        ->assertDontSee(__('services.admin.add_demo'))
        ->assertDontSee(__('services.admin.activate'));
});

it('lets the platform set service prices, demo length and bundle discounts', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListPlans::class)
        ->callAction('servicePrices', data: [
            'drive_thru' => ['price' => '399', 'demo_days' => 30, 'is_active' => true],
            'ready_call' => ['price' => '149', 'demo_days' => 14, 'is_active' => false],
            'rewards' => ['price' => '199', 'demo_days' => 14, 'is_active' => true],
            'bundle_two' => 12.5,
            'bundle_three' => 20,
        ])
        ->assertHasNoActionErrors();

    expect(ServicePrice::query()->where('service', 'drive_thru')->sole())
        ->price_minor->toBe(39900)->demo_days->toBe(30)
        ->and(ServicePrice::query()->where('service', 'ready_call')->sole()->is_active)->toBeFalse()
        ->and(app(PlatformSettings::class)->int('billing.bundle_two_bp'))->toBe(1250)
        ->and(app(PlatformSettings::class)->int('billing.bundle_three_bp'))->toBe(2000);
});
