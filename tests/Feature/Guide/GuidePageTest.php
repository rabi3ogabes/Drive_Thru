<?php

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Guide\GuideCatalog;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\Guide;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function guideWorkspace(Restaurant $restaurant, ?User $user = null): User
{
    $user ??= ownerOf($restaurant);
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    return $user;
}

it('shows start here, every section and the articles in the owner language', function (string $locale, string $start, string $section, string $article) {
    app()->setLocale($locale);
    $restaurant = restaurant(slug: 'masar');
    guideWorkspace($restaurant);

    Livewire::test(Guide::class)
        ->assertOk()
        ->assertSee($start)
        ->assertSee($section)
        ->assertSee($article)
        ->assertSeeHtml('id="guide-ratings"')
        ->assertSeeHtml('data-guide-search');
})->with([
    'English' => ['en', 'Start here', 'Branch screens', 'Pickup window'],
    'Arabic' => ['ar', 'ابدأ من هنا', 'شاشات الفرع', 'نافذة التسليم'],
]);

it('links each start-here step and article to its page', function () {
    app()->setLocale('en');
    $restaurant = restaurant(slug: 'masar');
    guideWorkspace($restaurant);

    Livewire::test(Guide::class)
        ->assertSeeHtml('href="'.e(GuideCatalog::find('settings')?->url($restaurant)).'"')
        ->assertSeeHtml('href="'.e(route('ops.go', [$restaurant, 'kitchen'])).'"')
        ->assertSeeHtml('href="'.e(route('public.restaurant', $restaurant)).'"');
});

it('marks articles for services the restaurant does not have as not on your plan', function () {
    app()->setLocale('en');
    $restaurant = restaurant(slug: 'masar', services: [Service::DriveThru]);
    guideWorkspace($restaurant);

    $entries = collect(Guide::entries())->keyBy('key');

    expect($entries['screen_pager']['available'])->toBeFalse()
        ->and($entries['screen_pager']['url'])->toBeNull()
        ->and($entries['screen_kitchen']['available'])->toBeTrue()
        ->and($entries['screen_kitchen']['url'])->not->toBeNull();

    Livewire::test(Guide::class)->assertSee('Not on your plan');
});

it('hides open this page when the user cannot open it', function () {
    app()->setLocale('en');
    $restaurant = restaurant(slug: 'masar');
    guideWorkspace($restaurant, employeeAt($restaurant, 'cashier'));

    $entries = collect(Guide::entries())->keyBy('key');

    expect($entries['billing']['url'])->toBeNull()
        ->and($entries['billing']['who'])->toBe(RestaurantPermission::ManageBilling->getLabel())
        ->and($entries['dashboard']['url'])->not->toBeNull()
        ->and($entries['guest_ordering']['url'])->not->toBeNull();
});

it('shows a help button on workspace pages that opens that page article', function () {
    app()->setLocale('en');
    $restaurant = restaurant(slug: 'masar');
    $this->actingAs(ownerOf($restaurant));

    $this->get('/app/masar/ratings')
        ->assertOk()
        ->assertSee('data-guide="ratings"', false)
        ->assertSee('What guests said after pickup', false)
        ->assertSee('Read the full guide');

    $this->get('/app/masar/guide')->assertOk()->assertDontSee('data-guide="', false);
});

it('keeps the guide to signed-in workspace users', function () {
    restaurant(slug: 'masar');

    $this->get('/app/masar/guide')->assertRedirect('/app/login');
});
