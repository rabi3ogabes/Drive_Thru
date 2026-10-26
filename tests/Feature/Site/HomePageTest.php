<?php

use App\Domain\Growth\Models\Lead;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Models\ServicePrice;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('presents the three services with their real prices, in both languages', function (string $locale) {
    $response = $this->withHeader('Accept-Language', $locale)->get('/?lang='.$locale)->assertOk();

    $response->assertSee(__('site.hero.title', [], $locale))
        ->assertSee(__('site.services.drive_thru.title', [], $locale))
        ->assertSee(__('site.services.ready_call.title', [], $locale))
        ->assertSee(__('site.services.rewards.title', [], $locale))
        ->assertSee(__('site.pricing.title', [], $locale))
        ->assertSee('349.00', false)
        ->assertSee('"@type":"SoftwareApplication"', false);
})->with(['en', 'ar']);

it('takes prices, demo length and bundle discounts from the platform price list', function () {
    ServicePrice::query()->where('service', 'drive_thru')->update(['price_minor' => 42000, 'demo_days' => 30]);
    ServicePrice::query()->where('service', 'ready_call')->update(['is_active' => false]);
    app(PlatformSettings::class)->set(['billing.bundle_two_bp' => 1250]);

    $this->get('/')->assertOk()
        ->assertSee('420.00', false)
        ->assertSee(__('site.hero.trust.free', ['days' => 30]))
        ->assertSee(__('site.pricing.bundle_hint', ['two' => '12.5', 'three' => '15']))
        ->assertDontSee('149.00', false);
});

it('loads nothing from other websites', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/', (string) $html, $matches);
    $external = array_filter($matches[1], fn (string $url): bool => ! str_starts_with($url, config('app.url')) && ! str_starts_with($url, 'http://localhost'));

    expect($external)->toBe([]);
});

it('keeps the services a prospect picked on the lead, and ignores made-up ones', function () {
    $this->post('/request-access', [
        'restaurant_name' => 'Karak Corner',
        'contact_name' => 'Noora',
        'email' => 'noora@karak.test',
        'branches_count' => 2,
        'services' => ['ready_call', 'rewards'],
    ])->assertRedirect(route('home').'#request');

    expect(Lead::sole()->services)->toBe(['ready_call', 'rewards']);

    $this->post('/request-access', [
        'restaurant_name' => 'Other', 'contact_name' => 'X', 'email' => 'x@other.test',
        'services' => ['teleport'],
    ])->assertSessionHasErrors('services.0');
});
