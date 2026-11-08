<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Menu\MenuCache;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\MenuTheme;

/**
 * A restaurant's theme: its template with the owner's changes on top.
 *
 * Restaurants that never saved a theme keep their current look, read from
 * their brand colour and menu layout. A template the admin hid still
 * applies to restaurants already using it; a missing one, or changes that
 * no longer validate, fall back to the template or the default.
 *
 * Scoped: templates load once per request.
 */
final class ThemeResolver
{
    /** @var array<string, MenuTheme>|null */
    private ?array $templates = null;

    /** @var array<string, ThemeSpec> */
    private array $specs = [];

    public static function for(Restaurant $restaurant): ThemeSpec
    {
        return app(self::class)->resolve($restaurant);
    }

    public static function template(Restaurant $restaurant): MenuTheme
    {
        return app(self::class)->templateFor($restaurant);
    }

    /** Stores what differs from the template, keeps the brand colour in sync and refreshes guest pages. */
    public static function save(Restaurant $restaurant, string $templateKey, ThemeSpec $spec): void
    {
        $template = MenuTheme::query()->where('key', $templateKey)->firstOrFail();
        $overrides = $spec->diff($template->spec());
        unset($overrides['cover']);

        $restaurant->forceFill([
            'menu_theme' => ['template' => $template->key, 'overrides' => $overrides, 'updated_at' => now()->toIso8601String()],
            'accent_color' => $spec->colors['brand'],
            'menu_layout' => $spec->layout->toArray(),
        ])->save();

        MenuCache::bump($restaurant->id);
        app(self::class)->forget();
    }

    public function resolve(Restaurant $restaurant): ThemeSpec
    {
        $memo = $restaurant->id.':'.md5((string) json_encode([$restaurant->menu_theme, $restaurant->accent_color, $restaurant->menu_layout, $restaurant->cover_path]));

        return $this->specs[$memo] ??= $this->build($restaurant);
    }

    public function templateFor(Restaurant $restaurant): MenuTheme
    {
        $templates = $this->templates();
        // Restaurants that never saved a theme keep the classic look, whatever the default is now.
        $key = is_array($restaurant->menu_theme) ? ($restaurant->menu_theme['template'] ?? null) : MenuTheme::CLASSIC;

        return (is_string($key) ? $templates[$key] ?? null : null) ?? MenuTheme::default();
    }

    public function forget(): void
    {
        $this->templates = null;
        $this->specs = [];
    }

    private function build(Restaurant $restaurant): ThemeSpec
    {
        $base = $this->templateFor($restaurant)->spec();
        $theme = $restaurant->menu_theme;

        $overrides = is_array($theme)
            ? (is_array($theme['overrides'] ?? null) ? $theme['overrides'] : [])
            : array_filter([
                'colors' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $restaurant->accent_color) === 1 ? ['brand' => $restaurant->accent_color] : null,
                'layout' => is_array($restaurant->menu_layout) ? $restaurant->menu_layout : null,
            ]);

        try {
            $spec = $base->with($overrides, $restaurant);
        } catch (ThemeRejected) {
            $spec = $base;
        }

        return filled($restaurant->cover_path) ? $spec->with(['cover' => $restaurant->cover_path], $restaurant) : $spec;
    }

    /**
     * @return array<string, MenuTheme>
     */
    private function templates(): array
    {
        return $this->templates ??= MenuTheme::query()->get()->keyBy('key')->all();
    }
}
