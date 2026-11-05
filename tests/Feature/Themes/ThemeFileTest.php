<?php

use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeFile;

function oliveFile(array $spec = [], array $extra = []): string
{
    $olive = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->spec();
    $data = json_decode(ThemeFile::export($olive, ['en' => 'Olive Grove', 'ar' => 'بستان الزيتون'], 'olive-grove'), true);
    $data['spec'] = array_replace_recursive($data['spec'], $spec);

    return json_encode($data + $extra);
}

function rejection(string $contents): string
{
    try {
        ThemeFile::parse($contents);
    } catch (ThemeRejected $e) {
        return $e->reason;
    }

    return 'accepted';
}

it('round-trips a theme', function () {
    $olive = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->spec();
    $parsed = ThemeFile::parse(ThemeFile::export($olive, ['en' => 'Olive Grove', 'ar' => 'بستان الزيتون'], 'olive-grove'));

    expect($parsed['spec']->toArray())->toBe($olive->toArray())
        ->and($parsed['name'])->toBe(['en' => 'Olive Grove', 'ar' => 'بستان الزيتون'])
        ->and($parsed['base'])->toBe('olive-grove')
        ->and($parsed['notices'])->toBe([]);
});

it('refuses files over 20 KB', function () {
    expect(rejection(oliveFile(extra: ['padding' => str_repeat('x', 21 * 1024)])))->toBe('too_large');
});

it('refuses non-JSON, other formats and newer versions', function () {
    $newer = json_decode(oliveFile(), true);
    $newer['version'] = 2;
    $other = json_decode(oliveFile(), true);
    $other['format'] = 'figma';

    expect(rejection('<?php echo 1;'))->toBe('not_json')
        ->and(rejection('[1, 2]'))->toBe('not_theme')
        ->and(rejection(json_encode($other)))->toBe('not_theme')
        ->and(rejection(json_encode($newer)))->toBe('newer_version');
});

it('refuses CSS injection in colours', function () {
    expect(rejection(oliveFile(['colors' => ['brand' => '#fff;}body{display:none}']])))->toBe('bad_color')
        ->and(rejection(oliveFile(['colors' => ['text' => 'url(https://evil.test/x)']])))->toBe('bad_color');
});

it('refuses unknown fonts and values', function () {
    expect(rejection(oliveFile(['fonts' => ['heading' => "geist+alexandria'); }"]])))->toBe('unknown_font')
        ->and(rejection(oliveFile(['corners' => 'blob'])))->toBe('bad_value');
});

it('strips tags from names and caps them at 60 characters', function () {
    $data = json_decode(oliveFile(), true);
    $data['name'] = ['en' => '<script>alert(1)</script>Olive', 'ar' => str_repeat('ز', 80)];

    $parsed = ThemeFile::parse(json_encode($data));

    expect($parsed['name']['en'])->toBe('alert(1)Olive')
        ->and(mb_strlen($parsed['name']['ar']))->toBe(60);
});

it('ignores unknown fields', function () {
    $parsed = ThemeFile::parse(oliveFile(['shadow' => 'huge', 'colors' => ['glow' => '#ffffff']], ['author' => 'someone']));

    expect($parsed['spec']->toArray())->not->toHaveKey('shadow')
        ->and($parsed['spec']->colors)->not->toHaveKey('glow');
});

it('drops brand fonts from another restaurant with a notice', function () {
    $parsed = ThemeFile::parse(oliveFile(['fonts' => ['heading' => 'brand:99']]), restaurant());
    $olive = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->spec();

    expect($parsed['spec']->fonts['heading'])->toBe($olive->fonts['heading'])
        ->and($parsed['notices'])->toBe([__('themes.import.font_dropped', ['role' => __('themes.fonts.heading')])]);
});

it('refuses an unreadable theme with the measured ratio', function () {
    try {
        ThemeFile::parse(oliveFile(['colors' => ['text' => '#e8e6df']]));
        $this->fail('accepted');
    } catch (ThemeRejected $e) {
        expect($e->reason)->toBe('unreadable')
            ->and($e->context['ratio'])->toBeLessThan(4.5);
    }
});

it('exports the spec format', function () {
    $olive = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->spec();
    $data = json_decode(ThemeFile::export($olive, ['en' => 'Olive', 'ar' => 'زيتون'], 'olive-grove'), true);

    expect($data['format'])->toBe('drive-thru-theme')
        ->and($data['version'])->toBe(1)
        ->and(array_keys($data['spec']))->toBe(['mode', 'colors', 'fonts', 'corners', 'layout']);
});
