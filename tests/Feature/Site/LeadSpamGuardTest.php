<?php

use App\Domain\Billing\PlanCatalog;
use App\Domain\Growth\Models\Lead;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__.'/../../Support/LeadHelpers.php';

beforeEach(function () {
    Notification::fake();
    app(PlanCatalog::class)->install();
    RateLimiter::clear('leads:127.0.0.1');
});

function leadForm(array $overrides = []): array
{
    return array_merge([
        'restaurant_name' => 'Al Wakra Shawarma',
        'contact_name' => 'Saeed',
        'email' => 'saeed@wakra.test',
        'message' => 'Two lanes, busy at night.',
        'form_started' => leadToken(),
    ], $overrides);
}

it('puts the timing token in the request form', function () {
    $this->get('/')->assertOk()->assertSeeHtml('name="form_started"');
});

it('accepts a real request sent after a few seconds', function () {
    $this->post('/request-access', leadForm())->assertRedirect()->assertSessionHas('lead_submitted');

    expect(Lead::count())->toBe(1);
});

it('silently drops a form sent too fast, or without its token', function (?string $token) {
    $this->post('/request-access', leadForm(['form_started' => $token]))->assertRedirect()->assertSessionHas('lead_submitted');

    expect(Lead::count())->toBe(0);
})->with([
    'sent within a second' => fn () => leadToken(1),
    'no token' => null,
    'forged token' => 'not-a-real-token',
]);

it('silently drops SEO and marketing pitches', function (string $message) {
    $this->post('/request-access', leadForm(['message' => $message]))->assertRedirect()->assertSessionHas('lead_submitted');

    expect(Lead::count())->toBe(0);
})->with([
    'Hi Feature dhawq.net in Google\'s Search Index and have it show up in Google search results!',
    'Hey team dhawq.net, I would like to discuss AI SEO! We help businesses rank higher on Google.',
    'Hello http://dhawq.net, We can place your website on Google 1st page.',
    'Hi, I just visited dhawq.net and wondered if you\'ve ever considered an impactful video to advertise your business?',
    'We recently built a free Instagram report tool: https://example.test/report',
]);

it('silently drops lookalike addresses of our own domain', function () {
    $this->post('/request-access', leadForm(['email' => 'mirta@search-dhawq.net']))->assertRedirect()->assertSessionHas('lead_submitted');

    expect(Lead::count())->toBe(0);
});

it('keeps a real request that mentions the restaurant\'s own website', function () {
    $this->post('/request-access', leadForm(['message' => 'Our menu is on www.wakrashawarma.qa, we have two lanes.']))->assertRedirect();

    expect(Lead::count())->toBe(1);
});

it('limits one email address to three requests a day', function () {
    foreach (range(1, 3) as $i) {
        RateLimiter::clear('leads:127.0.0.1');
        $this->post('/request-access', leadForm(['restaurant_name' => "R{$i}"]))->assertRedirect();
    }

    RateLimiter::clear('leads:127.0.0.1');
    $this->post('/request-access', leadForm(['restaurant_name' => 'R4']))->assertStatus(429);
});

it('skips the daily billing run while billing is switched off', function (bool $on) {
    config(['billing.scheduled' => $on]);

    $event = collect(app(Schedule::class)->events())->first(fn ($e): bool => str_contains((string) $e->command, 'billing:run'));

    expect($event)->not->toBeNull()
        ->and($event->filtersPass(app()))->toBe($on);
})->with([true, false]);
