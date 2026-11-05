<?php

use App\Domain\Themes\Contrast;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Palette;

const THEME_STEPS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

it('builds 11 deterministic shades in gamut', function () {
    $shades = Palette::shades('#0f3b3a', THEME_STEPS);

    expect($shades)->toBe(Palette::shades('#0f3b3a', THEME_STEPS))
        ->and($shades)->toHaveCount(11)
        ->and(array_keys($shades))->toBe(THEME_STEPS);

    foreach ($shades as $hex) {
        expect($hex)->toMatch('/^#[0-9a-f]{6}$/');
    }

    $lightness = array_map(fn (string $hex): float => Palette::oklch($hex)[0], array_values($shades));
    for ($i = 1; $i < count($lightness); $i++) {
        expect($lightness[$i])->toBeLessThan($lightness[$i - 1]);
    }

    $hueDelta = abs(Palette::oklch($shades[500])[2] - Palette::oklch('#0f3b3a')[2]);
    expect(min($hueDelta, 360 - $hueDelta))->toBeLessThan(3.0);
});

it('measures WCAG contrast', function () {
    expect(Contrast::ratio('#ffffff', '#000000'))->toEqualWithDelta(21.0, 0.01)
        ->and(Contrast::ratio('#777777', '#ffffff'))->toEqualWithDelta(4.48, 0.01)
        ->and(Contrast::ratio('#000000', '#ffffff'))->toEqualWithDelta(21.0, 0.01);
});

it('picks readable button text', function () {
    expect(Contrast::onColor('#0f3b3a', '#111827'))->toBe('#ffffff')
        ->and(Contrast::onColor('#f5d76e', '#111827'))->toBe('#111827');
});

it('normalises colours and rejects anything else', function (string $input) {
    expect(fn () => Palette::normalize($input))->toThrow(ThemeRejected::class);
})->with(['red', '#12345', '#fff;}body{', '#gggggg', '']);

it('lower-cases valid colours', function () {
    expect(Palette::normalize('#0F3B3A'))->toBe('#0f3b3a');
});
