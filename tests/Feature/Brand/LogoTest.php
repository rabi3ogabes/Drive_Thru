<?php

use App\Support\Brand;
use Illuminate\Support\Facades\Blade;

const DHAL_PATH = 'M14 16c6 .6 11 3.6 12.2 8.4';

it('gives each mark its own gradient id', function () {
    $html = Blade::render('<x-brand.mark /><x-brand.mark />');

    preg_match_all('/id="(dhawq-mark-[^"]+)"/', $html, $ids);

    expect($ids[1])->toHaveCount(2)
        ->and($ids[1][0])->not->toBe($ids[1][1])
        ->and($html)->toContain(DHAL_PATH);
});

it('shows the wordmark in the page language', function () {
    app()->setLocale('ar');
    $ar = Blade::render('<x-brand.logo />');
    expect(mb_strpos($ar, 'ذوق'))->toBeLessThan(mb_strpos($ar, 'DHAWQ'));

    app()->setLocale('en');
    $en = Blade::render('<x-brand.logo />');
    expect(mb_strpos($en, 'DHAWQ'))->toBeLessThan(mb_strpos($en, 'ذوق'));
});

it('draws the mark in one colour for print', function () {
    $html = Blade::render('<x-brand.mark :monochrome="true" />');

    expect($html)->toContain('currentColor')->and($html)->not->toContain('url(#');
});

it('glows the dot only when asked, and never for reduced-motion visitors', function () {
    expect(Blade::render('<x-brand.mark :animated="true" />'))->toContain('brand-dot-glow')
        ->and(Blade::render('<x-brand.mark />'))->not->toContain('brand-dot-glow');

    $css = (string) file_get_contents(resource_path('css/app.css'));
    expect($css)->toMatch('/@media \(prefers-reduced-motion: no-preference\)\s*\{[^}]*\.brand-dot-glow/s');
});

it('puts the mark and both names on the invoice and the QR posters', function () {
    foreach (['invoice', 'qr-poster'] as $view) {
        $source = (string) file_get_contents(resource_path("views/pdf/{$view}.blade.php"));

        expect($source)->toContain('Brand::markDataUri()')
            ->and($source)->toContain('Brand::full()');
    }

    // The 5 × 5 cm sticker has room for the name only.
    expect((string) file_get_contents(resource_path('views/pdf/qr-sticker.blade.php')))->toContain('Brand::full()');
});

it('ships the generated brand assets', function () {
    foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/maskable-512.png', 'images/site/og.png'] as $file) {
        expect(public_path($file))->toBeFile();
    }

    expect(getimagesize(public_path('images/site/og.png'))[0])->toBe(1200)
        ->and(getimagesize(public_path('images/site/og.png'))[1])->toBe(630)
        ->and(getimagesize(public_path('icons/icon-512.png'))[0])->toBe(512)
        ->and(file_get_contents(public_path('favicon.svg')))->toContain(DHAL_PATH)
        ->and(file_get_contents(resource_path('brand/dhawq-mark.svg')))->toContain(DHAL_PATH);
});

it('points the site head at the new icons and share image', function () {
    $this->get('/')->assertOk()
        ->assertSee('apple-touch-icon.png', false)
        ->assertSee(asset('images/site/og.png'), false)
        ->assertSee(Brand::latin());
});
