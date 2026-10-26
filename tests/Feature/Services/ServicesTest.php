<?php

use App\Domain\Billing\Actions\RunBillingCycle;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Cart;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Actions\ManageServices;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Exceptions\ServiceProblem;
use App\Domain\Services\Models\RestaurantService;
use App\Domain\Services\Models\ServicePrice;
use App\Domain\Services\Notifications\ServiceChanged;
use App\Domain\Services\ServicePricing;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\BranchMenu;
use App\Livewire\Customer\Checkout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    app()->setLocale('en');
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'Asia/Qatar'));
});

/** A restaurant with no services yet, its owner, and a monthly period from 1 Oct to 1 Nov. */
function bareRestaurant(int $branches = 1): array
{
    $restaurant = restaurant(slug: 'shop-'.uniqid(), services: []);
    $owner = ownerOf($restaurant);
    Branch::factory()->count($branches)->create(['restaurant_id' => $restaurant->id]);
    $plan = Plan::factory()->create(['price_minor' => 0]);
    Subscription::withoutTenancy()->create([
        'restaurant_id' => $restaurant->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active,
        'quantity' => $branches, 'unit_price_minor' => 0, 'currency' => 'QAR', 'interval' => 'month',
        'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
    ]);

    return [$restaurant->fresh(), $owner];
}

function platformAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

it('switches each service on and off by status', function (ServiceStatus $status, bool $on) {
    [$restaurant] = bareRestaurant();
    withService($restaurant, Service::Rewards, $status);

    expect($restaurant->hasService(Service::Rewards))->toBe($on)
        ->and($restaurant->hasFeature(PlanFeature::Loyalty))->toBe($on)
        ->and($restaurant->hasService(Service::ReadyCall))->toBeFalse();
})->with([
    'demo' => [ServiceStatus::Demo, true],
    'active' => [ServiceStatus::Active, true],
    'cancelling' => [ServiceStatus::Cancelling, true],
    'ended' => [ServiceStatus::Ended, false],
]);

it('keeps guests out of online ordering without Drive-Thru, but still shows the menu', function () {
    [$restaurant] = bareRestaurant();
    $branch = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->first();
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 2500]);

    Livewire::test(BranchMenu::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->assertSee(__('guest.menu.menu_only'))
        ->call('add', $product->id, 1)
        ->assertDispatched('toast', tone: 'error');
    expect(app(Cart::class)->lines($restaurant->id))->toBe([]);

    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->assertRedirect(route('public.branch', [$restaurant, $branch->code]));

    withService($restaurant, Service::DriveThru);
    Livewire::test(BranchMenu::class, ['restaurant' => $restaurant->fresh(), 'branch' => $branch->code])
        ->assertDontSee(__('guest.menu.menu_only'))
        ->call('add', $product->id, 1);
    expect(app(Cart::class)->lines($restaurant->id))->toHaveCount(1);
});

it('offers a demo once, free, and lets the platform grant another on purpose', function () {
    [$restaurant, $owner] = bareRestaurant();
    $services = app(ManageServices::class);

    $row = $services->startDemo($restaurant, Service::Rewards, $owner);

    expect($row->status)->toBe(ServiceStatus::Demo)
        ->and($row->demo_ends_at->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($row->unit_price_minor)->toBe(19900)
        ->and($restaurant->hasService(Service::Rewards))->toBeTrue()
        ->and(Invoice::withoutTenancy()->count())->toBe(0);
    Notification::assertSentTo($owner, ServiceChanged::class, fn (ServiceChanged $n): bool => $n->kind === 'demo_started');

    $services->cancel($restaurant, Service::Rewards, $owner);
    expect($restaurant->hasService(Service::Rewards))->toBeFalse();

    expect(fn () => $services->startDemo($restaurant, Service::Rewards, $owner))
        ->toThrow(ServiceProblem::class, __('services.errors.demo_used', ['service' => 'Rewards']));

    $services->startDemo($restaurant, Service::Rewards, platformAdmin(), days: 30, grantAnother: true, reason: 'Big chain, needs a month');
    expect($restaurant->service(Service::Rewards)->demo_ends_at->toDateString())->toBe(now()->addDays(30)->toDateString());

    $granted = Activity::query()->where('event', 'services.demo_granted')->sole();
    expect($granted->properties['reason'])->toBe('Big chain, needs a month');
});

it('quotes branches × services with the bundle discount', function (array $services, int $branches, int $total) {
    $quote = app(ServicePricing::class)->listQuote($services, $branches);

    expect($quote['total_minor'])->toBe($total);
})->with([
    'one service, one branch' => [[Service::DriveThru], 1, 34900],
    'two services, two branches, 10% off' => [[Service::DriveThru, Service::Rewards], 2, (int) round((34900 + 19900) * 2 * 0.9)],
    'three services, one branch, 15% off' => [[Service::DriveThru, Service::ReadyCall, Service::Rewards], 1, 69700 - 10455],
]);

it('leaves a service that is not on sale out of the quote', function () {
    ServicePrice::query()->where('service', 'ready_call')->update(['is_active' => false]);

    expect(app(ServicePricing::class)->listQuote([Service::ReadyCall, Service::Rewards], 1)['total_minor'])->toBe(19900);
});

it('bills the rest of the period when a service is added, including the bundle change', function (string $day, int $daysLeft) {
    [$restaurant, $owner] = bareRestaurant(branches: 2);
    $services = app(ManageServices::class);
    withService($restaurant, Service::DriveThru, priceMinor: 34900);

    $this->travelTo(CarbonImmutable::parse("2026-10-{$day} 10:00", 'Asia/Qatar'));
    $services->activate($restaurant, Service::Rewards, $owner);

    // Before: 34,900 × 2 = 69,800. After: (34,900 + 19,900) × 2 = 109,600, less 10% = 98,640.
    $expected = (int) round((98640 - 69800) * $daysLeft / 31);
    $invoice = Invoice::withoutTenancy()->with('lines')->sole();

    expect($invoice->subtotal_minor)->toBe($expected)
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->getTranslation('description', 'en'))->toBe("Rewards · 2 branches · {$daysLeft} days");
})->with([
    'the first day' => ['01', 31],
    'mid-month' => ['15', 17],
    'the last day' => ['31', 1],
]);

it('invoices each service per branch with the bundle discount line at renewal', function () {
    [$restaurant] = bareRestaurant(branches: 3);
    withService($restaurant, Service::DriveThru, priceMinor: 34900);
    withService($restaurant, Service::ReadyCall, priceMinor: 14900);
    withService($restaurant, Service::Rewards, ServiceStatus::Demo, priceMinor: 19900, attributes: ['demo_ends_at' => now()->addDays(20)]);

    $this->travelTo(now()->addMonth()->addHour());
    app(RunBillingCycle::class)->handle();

    $invoice = Invoice::withoutTenancy()->with('lines')->sole();
    $gross = (34900 + 14900) * 3;
    $discount = (int) round($gross * 0.10);

    expect($invoice->lines->pluck('is_discount')->all())->toBe([false, false, true])
        ->and($invoice->lines->first()->quantity)->toBe(3)
        ->and($invoice->subtotal_minor)->toBe($gross - $discount);
});

it('converts a kept demo once, ends the others, and reminds three days before', function () {
    [$restaurant, $owner] = bareRestaurant();
    $services = app(ManageServices::class);
    $services->startDemo($restaurant, Service::Rewards, $owner);
    $services->startDemo($restaurant, Service::ReadyCall, $owner);
    $services->keepAfterDemo($restaurant, Service::Rewards, true, $owner);

    $this->travelTo(now()->addDays(12));
    app(RunBillingCycle::class)->handle();
    app(RunBillingCycle::class)->handle();
    Notification::assertSentToTimes($owner, ServiceChanged::class, 4); // 2 started + 2 reminders, once each

    $this->travelTo(now()->addDays(2)->addHour());
    $first = app(RunBillingCycle::class)->handle();
    $second = app(RunBillingCycle::class)->handle();

    expect($first['services'])->toMatchArray(['converted' => 1, 'demos_ended' => 1])
        ->and($second['services'])->toMatchArray(['converted' => 0, 'demos_ended' => 0])
        ->and($restaurant->fresh()->service(Service::Rewards)->status)->toBe(ServiceStatus::Active)
        ->and($restaurant->fresh()->hasService(Service::ReadyCall))->toBeFalse()
        ->and(Invoice::withoutTenancy()->count())->toBe(1); // the rest of the period for Rewards, once
});

it('cancels at period end without refund, and can be undone before then', function () {
    [$restaurant, $owner] = bareRestaurant();
    $services = app(ManageServices::class);
    $services->activate($restaurant, Service::Rewards, $owner);

    $row = $services->cancel($restaurant, Service::Rewards, $owner);
    expect($row->status)->toBe(ServiceStatus::Cancelling)
        ->and($row->ends_at->toDateString())->toBe('2026-11-01')
        ->and($restaurant->hasService(Service::Rewards))->toBeTrue();

    $services->resume($restaurant, Service::Rewards, $owner);
    $services->cancel($restaurant, Service::Rewards, $owner);

    $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00', 'Asia/Qatar'));
    app(RunBillingCycle::class)->handle();

    expect($restaurant->fresh()->hasService(Service::Rewards))->toBeFalse()
        ->and(Invoice::withoutTenancy()->where('subtotal_minor', '>', 0)->count())->toBe(1); // only the prorated one
});

it('lets the platform change a client price, extend a demo and end a service, all audited', function () {
    [$restaurant, $owner] = bareRestaurant();
    $admin = platformAdmin();
    $services = app(ManageServices::class);

    $services->startDemo($restaurant, Service::DriveThru, $admin, days: 7);
    $services->extendDemo($restaurant, Service::DriveThru, 7, $admin, 'Waiting for their POS');
    $services->setPrice($restaurant, Service::DriveThru, 29900, $admin, 'Launch partner');
    $services->end($restaurant, Service::DriveThru, $admin, 'Asked to stop');

    $row = $restaurant->fresh()->service(Service::DriveThru);
    expect($row->status)->toBe(ServiceStatus::Ended)
        ->and($row->unit_price_minor)->toBe(29900)
        ->and(Activity::query()->where('event', 'like', 'services.%')->pluck('event')->all())
        ->toBe(['services.demo_started', 'services.demo_extended', 'services.price_changed', 'services.ended']);
});

it('keeps services apart between restaurants', function () {
    [$one] = bareRestaurant();
    [$two] = bareRestaurant();
    withService($one, Service::Rewards);

    expect($one->hasService(Service::Rewards))->toBeTrue()
        ->and($two->hasService(Service::Rewards))->toBeFalse()
        ->and(RestaurantService::withoutTenancy()->where('restaurant_id', $two->id)->count())->toBe(0);
});

it('never invoices a negative total', function () {
    app(PlatformSettings::class)->set(['billing.bundle_three_bp' => 10000]);
    [$restaurant] = bareRestaurant();

    foreach (Service::cases() as $service) {
        withService($restaurant, $service, priceMinor: 100);
    }

    expect(app(ServicePricing::class)->restaurantQuote($restaurant)['total_minor'])->toBe(0)
        ->and(Restaurant::query()->find($restaurant->id)->subscription->amountPerPeriodMinor())->toBe(0);
});
