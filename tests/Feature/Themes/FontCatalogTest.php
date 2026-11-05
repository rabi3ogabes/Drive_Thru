<?php

use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\FontCatalog;

it('lists 16 fonts, 8 per script, each with files that exist in public/fonts/guest', function () {
    $fonts = FontCatalog::all();

    expect($fonts)->toHaveCount(16)
        ->and(collect($fonts)->where('script', 'latin'))->toHaveCount(8)
        ->and(collect($fonts)->where('script', 'arabic'))->toHaveCount(8);

    foreach ($fonts as $key => $font) {
        expect($font['faces'])->not->toBeEmpty();
        foreach ($font['faces'] as $face) {
            expect(public_path("fonts/guest/{$key}/{$face['file']}"))->toBeFile();
        }
    }
});

it('emits font-face only for the requested fonts, all local, all swap', function () {
    $css = FontCatalog::fontFaceCss(['geist', 'tajawal']);

    expect($css)->toContain("font-family: 'dt-geist'")
        ->and($css)->toContain("font-family: 'dt-tajawal'")
        ->and($css)->not->toContain('amiri')
        ->and($css)->toContain('font-display: swap')
        ->and(preg_match_all('/url\(([^)]+)\)/', $css, $urls))->toBeGreaterThan(0);

    foreach ($urls[1] as $url) {
        expect($url)->toStartWith(asset('fonts/guest/'));
    }
});

it('splits a pair and builds a family stack with a fallback', function () {
    expect(FontCatalog::pair('fraunces+alexandria'))->toBe(['latin' => 'fraunces', 'arabic' => 'alexandria'])
        ->and(FontCatalog::stack('fraunces+alexandria'))->toBe("'dt-fraunces', 'dt-alexandria', ui-serif, Georgia, serif");
});

it('rejects an unknown pair or a pair in the wrong order', function (string $pair) {
    expect(fn () => FontCatalog::pair($pair))->toThrow(ThemeRejected::class);
})->with(['comic+sans', 'alexandria+fraunces', 'geist', 'geist+geist']);
