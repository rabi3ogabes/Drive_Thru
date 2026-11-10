<?php

use App\Support\Brand;
use Illuminate\Support\Js;

it('opens the home page on three service slides', function () {
    app()->setLocale('en');
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'aria-roledescription="slide"'))->toBe(3)
        ->and($html)->toContain('aria-roledescription="carousel"')
        ->and(strpos($html, 'Orders that meet the car'))->toBeLessThan(strpos($html, 'A pager in every pocket'))
        ->and(strpos($html, 'A pager in every pocket'))->toBeLessThan(strpos($html, 'Regulars, rewarded'))
        ->and($html)->not->toContain('id="services"')
        ->and($html)->toContain('<title>'.e(__('site.meta.title')).'</title>')
        ->and($html)->toContain('"name":"'.Brand::latin().'"');
});

it('opens the Arabic home page on the Arabic slides', function () {
    $html = $this->withSession(['locale' => 'ar'])->get('/?lang=ar')->assertOk()->getContent();

    foreach (['drive_thru', 'ready_call', 'rewards'] as $key) {
        expect($html)->toContain(e(__("site.hero.slides.{$key}.title", [], 'ar')));
    }
});

it('renders the first slide visible and the others inert without JavaScript', function () {
    $html = $this->get('/')->getContent();

    preg_match_all('/<article id="hero-(\w+)"[^>]*>/', $html, $slides);

    expect($slides[1])->toBe(['drive_thru', 'ready_call', 'rewards'])
        ->and($slides[0][0])->toContain('is-active')->and($slides[0][0])->not->toContain(' inert')
        ->and($slides[0][1])->toContain(' inert')->and($slides[0][2])->toContain(' inert')
        ->and($html)->toContain('href="#hero-ready_call"')
        ->and($html)->toContain('<h1');
});

it('sends each slide\'s main button to the request form with that service picked', function () {
    $html = $this->get('/')->getContent();

    foreach (['drive_thru', 'ready_call', 'rewards'] as $key) {
        expect($html)->toMatch('/href="[^"]*service='.$key.'#request"/');
    }

    $this->get('/?service=ready_call')->assertOk()->assertSee('services: '.Js::from(['ready_call'])->toHtml(), false);
});

it('ignores a service that is not offered in the preselection', function () {
    $this->get('/?service=nonsense')->assertOk()->assertSee('services: '.Js::from([])->toHtml(), false);
});

it('keeps motion off for reduced-motion visitors', function () {
    $js = (string) file_get_contents(resource_path('js/showcase.js'));

    // Reduced motion: nothing plays by itself, every scene shows its finished state.
    expect($js)->toContain("matchMedia('(prefers-reduced-motion: reduce)')")
        ->and($js)->toContain('if (this.still) return;');
});
