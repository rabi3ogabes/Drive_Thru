<?php

use App\Domain\Menu\MenuCache;
use App\Domain\Menu\MenuLayout;
use App\Domain\Themes\Contrast;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeCatalog;
use App\Domain\Themes\ThemeResolver;

it('installs six built-in templates once, with one default, and never overwrites admin edits', function () {
    expect(MenuTheme::query()->count())->toBe(6)
        ->and(MenuTheme::query()->where('is_default', true)->pluck('key')->all())->toBe(['ivory-teal'])
        ->and(MenuTheme::query()->where('is_built_in', true)->count())->toBe(6);

    MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->forceFill(['name' => ['en' => 'Our olive', 'ar' => 'زيتوننا']])->save();
    app(ThemeCatalog::class)->install();

    expect(MenuTheme::query()->count())->toBe(6)
        ->and(MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->getTranslation('name', 'en'))->toBe('Our olive');
});

it('ships only readable templates', function () {
    foreach (MenuTheme::query()->get() as $template) {
        expect(Contrast::check($template->spec())['ok'])->toBeTrue("{$template->key} is hard to read");
    }
});

it('keeps today\'s look for existing restaurants', function () {
    $restaurant = restaurant();
    $restaurant->forceFill(['accent_color' => '#224466', 'menu_layout' => ['card_style' => 'list'], 'menu_theme' => null])->save();

    $spec = ThemeResolver::for($restaurant->fresh());
    $template = MenuTheme::query()->where('key', 'ivory-teal')->firstOrFail()->spec();

    expect($spec->colors['brand'])->toBe('#224466')
        ->and($spec->layout->cardStyle)->toBe('list')
        ->and($template->colors)->toBe(['brand' => '#0f3b3a', 'accent' => '#b08d57', 'background' => '#fcfbf8', 'text' => '#111827'])
        ->and($template->fonts)->toBe(['heading' => 'fraunces+alexandria', 'body' => 'geist+ibm-plex-sans-arabic']);
});

it('merges template and overrides', function () {
    $restaurant = restaurant();
    $restaurant->forceFill(['menu_theme' => ['template' => 'olive-grove', 'overrides' => ['colors' => ['brand' => '#7a1f1f']]]])->save();

    $spec = ThemeResolver::for($restaurant->fresh());
    $olive = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->spec();

    expect($spec->colors['brand'])->toBe('#7a1f1f')
        ->and($spec->colors['background'])->toBe($olive->colors['background'])
        ->and($spec->fonts)->toBe($olive->fonts)
        ->and(ThemeResolver::template($restaurant->fresh())->key)->toBe('olive-grove');
});

it('still resolves a hidden template', function () {
    MenuTheme::query()->where('key', 'rose-cafe')->update(['is_active' => false]);
    $restaurant = restaurant();
    $restaurant->forceFill(['menu_theme' => ['template' => 'rose-cafe', 'overrides' => []]])->save();

    expect(ThemeResolver::template($restaurant->fresh())->key)->toBe('rose-cafe');
});

it('falls back to the default when the template is missing or the overrides are broken', function () {
    $restaurant = restaurant();
    $restaurant->forceFill(['menu_theme' => ['template' => 'gone', 'overrides' => ['colors' => ['brand' => 'javascript:']]]])->save();

    expect(ThemeResolver::template($restaurant->fresh())->key)->toBe('ivory-teal')
        ->and(ThemeResolver::for($restaurant->fresh())->colors['brand'])->toBe('#0f3b3a');
});

it('lets MenuLayout read the theme layout', function () {
    $restaurant = restaurant();
    $restaurant->forceFill(['menu_theme' => ['template' => 'mono-bold', 'overrides' => ['layout' => ['image_style' => 'none']]]])->save();

    expect(MenuLayout::for($restaurant->fresh())->imageStyle)->toBe('none');
});

it('saves only the differences, keeps the brand colour in sync and clears caches', function () {
    $restaurant = restaurant();
    $before = MenuCache::version($restaurant->id);
    $olive = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->spec();

    ThemeResolver::save($restaurant, 'olive-grove', $olive->with(['colors' => ['brand' => '#33441a']]));

    $fresh = $restaurant->fresh();
    expect($fresh->menu_theme['template'])->toBe('olive-grove')
        ->and($fresh->menu_theme['overrides'])->toBe(['colors' => ['brand' => '#33441a']])
        ->and($fresh->accent_color)->toBe('#33441a')
        ->and(MenuCache::version($restaurant->id))->toBeGreaterThan($before)
        ->and(ThemeResolver::for($fresh)->colors['brand'])->toBe('#33441a');
});
