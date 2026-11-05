<?php

use App\Domain\Menu\MenuLayout;
use App\Domain\Themes\Contrast;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\ThemeSpec;

function baseSpec(array $colors = []): ThemeSpec
{
    return new ThemeSpec(
        mode: 'light',
        colors: $colors + ['brand' => '#0f3b3a', 'accent' => '#c39e66', 'background' => '#fcfbf8', 'text' => '#111827'],
        fonts: ['heading' => 'fraunces+alexandria', 'body' => 'geist+ibm-plex-sans-arabic'],
        corners: 'rounded',
        layout: new MenuLayout,
    );
}

it('refuses unreadable text', function () {
    $check = Contrast::check(baseSpec(['text' => '#cccccc', 'background' => '#ffffff']));

    expect($check['ok'])->toBeFalse()
        ->and($check['errors'][0]['key'])->toBe('text_on_background')
        ->and($check['errors'][0]['ratio'])->toBeLessThan(4.5);
});

it('accepts a readable theme', function () {
    expect(Contrast::check(baseSpec())['ok'])->toBeTrue();
});

it('warns but allows a weak brand', function () {
    $check = Contrast::check(baseSpec(['brand' => '#a8a8a8', 'text' => '#5a5a5a', 'background' => '#ffffff']));

    expect($check['ok'])->toBeTrue()
        ->and(array_column($check['warnings'], 'key'))->toContain('button_text');
});

it('rejects bad colours', function () {
    ThemeSpec::fromArray(['colors' => ['brand' => '#fff;}body{']], baseSpec());
})->throws(ThemeRejected::class);

it('rejects unknown modes and corners', function (array $data) {
    expect(fn () => ThemeSpec::fromArray($data, baseSpec()))->toThrow(ThemeRejected::class);
})->with([[['mode' => 'neon']], [['corners' => 'wavy']], [['fonts' => ['heading' => 42]]]]);

it('refuses fonts outside the catalogue and display fonts for body text', function (array $fonts) {
    expect(fn () => ThemeSpec::fromArray(['fonts' => $fonts], baseSpec()))->toThrow(ThemeRejected::class);
})->with([[['heading' => 'comic+sans']], [['body' => 'fraunces+alexandria']], [['body' => 'geist+amiri']]]);

it('merges a partial over a base, and diff returns only changed fields', function () {
    $base = baseSpec();
    $spec = ThemeSpec::fromArray(['colors' => ['brand' => '#7A1F1F'], 'corners' => 'sharp'], $base);

    expect($spec->colors['brand'])->toBe('#7a1f1f')
        ->and($spec->colors['text'])->toBe('#111827')
        ->and($spec->corners)->toBe('sharp')
        ->and($spec->diff($base))->toBe(['colors' => ['brand' => '#7a1f1f'], 'corners' => 'sharp'])
        ->and(ThemeSpec::fromArray($spec->toArray())->toArray())->toBe($spec->toArray());
});

it('keeps layout changes in the diff', function () {
    $spec = ThemeSpec::fromArray(['layout' => ['card_style' => 'list']], baseSpec());

    expect($spec->layout->cardStyle)->toBe('list')
        ->and($spec->diff(baseSpec()))->toBe(['layout' => ['card_style' => 'list']]);
});
