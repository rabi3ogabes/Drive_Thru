<?php

use App\Domain\Growth\Models\Lead;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Models\ServicePrice;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('presents the three services with their real prices, in both languages', function (string $locale) {
    $response = $this->withHeader('Accept-Language', $locale)->get('/?lang='.$locale)->assertOk();

    $response->assertSee(__('site.hero.eyebrow', [], $locale))
        ->assertSee(__('site.hero.slides.drive_thru.title', [], $locale))
        ->assertSee(__('site.hero.slides.ready_call.title', [], $locale))
        ->assertSee(__('site.hero.slides.rewards.title', [], $locale))
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

it('opens on the services as an accessible hero carousel: one tab and one slide per service, each with its scene and page', function (string $locale) {
    $html = $this->get('/?lang='.$locale)->assertOk()->getContent();

    expect(substr_count($html, 'role="tab"'))->toBe(3)
        ->and(substr_count($html, 'role="tabpanel"'))->toBe(3)
        ->and($html)->toContain('aria-roledescription="carousel"')
        ->toContain('x-data="serviceShowcase(3)"')
        ->toContain('aria-controls="hero-ready_call"')
        ->toContain(route('site.service', 'drive-thru'))
        ->toContain(route('site.service', 'ready-call'))
        ->toContain(route('site.service', 'rewards'))
        ->toContain(e(__('site.showcase.drive_thru.paid', [], $locale)))
        ->toContain(e(__('site.showcase.ready_call.whatsapp', [], $locale)))
        ->toContain(e(__('site.showcase.rewards.wallet', [], $locale)));

    // Alpine makes the hidden slides inert; the server leaves them usable for visitors without JavaScript.
    expect(substr_count($html, ':inert="active !== '))->toBe(3);
})->with(['en', 'ar']);
