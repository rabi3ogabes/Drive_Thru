<?php

use App\Domain\Billing\PlanCatalog;
use App\Domain\Growth\Models\Lead;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Models\ServicePrice;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Js;

beforeEach(fn () => Notification::fake());

it('has a page for every service, in both languages, with its own form choice', function (Service $service, string $locale) {
    app()->setLocale($locale);
    $key = $service->value;

    $this->withHeader('Accept-Language', $locale)->get('/services/'.$service->slug().'?lang='.$locale)
        ->assertOk()
        ->assertSee(__("site.services.{$key}.title", [], $locale))
        ->assertSee(__("site.service_page.{$key}.features.0.title", [], $locale))
        ->assertSee(__("site.service_page.{$key}.flow.2.title", [], $locale))
        ->assertSee(__("site.service_page.{$key}.faq.0.q", [], $locale))
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        // The demo form arrives with this service already chosen.
        ->assertSee('services: '.Js::from([$key]), false);
})->with(Service::cases())->with(['en', 'ar']);

it('returns 404 for a service that does not exist', function () {
    $this->get('/services/teleport')->assertNotFound();
});

it('shows prices, the three services and the plan tiers on the pricing page', function () {
    app(PlanCatalog::class)->install();
    ServicePrice::query()->where('service', 'rewards')->update(['price_minor' => 24900]);

    $this->get('/pricing?lang=en')->assertOk()
        ->assertSee(__('site.pricing_page.title'))
        ->assertSee('249.00', false)
        ->assertSee(['Starter', 'Growth', 'Enterprise'])
        ->assertSee(__('site.pricing_page.tier_custom'))
        ->assertSee(__('site.pricing_page.billing.0.q'));
});

it('lists every public page in the sitemap, in both languages', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    $doc = simplexml_load_string((string) $xml);
    expect($doc)->not->toBeFalse()
        ->and(count($doc->url))->toBe(5 * 2);

    foreach (['/pricing', '/services/drive-thru', '/services/ready-call', '/services/rewards'] as $path) {
        expect((string) $xml)->toContain(url($path).'?lang=ar')->toContain(url($path).'?lang=en');
    }
});

it('sends visitors back to the page they asked from, never to another site', function () {
    $lead = ['restaurant_name' => 'Karak Corner', 'contact_name' => 'Noora', 'email' => 'noora@karak.test', 'services' => ['rewards']];

    $this->from(url('/services/rewards'))->post('/request-access', $lead)->assertRedirect(url('/services/rewards').'#request');
    $this->from('https://evil.test/phish')->post('/request-access', [...$lead, 'email' => 'other@karak.test'])->assertRedirect(route('home').'#request');
    $this->from(url('/admin'))->post('/request-access', [...$lead, 'email' => 'third@karak.test'])->assertRedirect(route('home').'#request');

    expect(Lead::query()->count())->toBe(3);
});

it('lets the website follow the device theme, and keeps guest pages light', function () {
    $this->get('/')->assertSee('class="site-themeable"', false)->assertSee('dtp.site.theme', false)->assertSee('content="light dark"', false);
    $this->get('/pricing')->assertSee('class="site-themeable"', false);

    $restaurant = restaurant(slug: 'karak');
    $this->get('/'.$restaurant->slug)->assertDontSee('site-themeable', false);
});

it('keeps the website paths away from restaurant links', function () {
    expect(CreateRestaurant::reservedSlugs())->toContain('services')->toContain('pricing');
});
