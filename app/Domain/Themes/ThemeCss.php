<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Menu\MenuCache;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Cache;

/**
 * The CSS a restaurant's theme adds to guest pages: custom properties that
 * override the stylesheet's tokens, plus @font-face for the fonts it uses.
 *
 * Only what differs from the stylesheet's own (classic) look is written,
 * so a restaurant on the classic theme renders exactly as before. Every
 * value comes from a validated spec; no free text reaches the CSS.
 */
final class ThemeCss
{
    /** The stylesheet's own look (resources/css/tokens.css). */
    public const CLASSIC = [
        'colors' => ['brand' => '#0f3b3a', 'accent' => '#b08d57', 'background' => '#fcfbf8', 'text' => '#111827'],
        'fonts' => ['heading' => 'fraunces+alexandria', 'body' => 'geist+ibm-plex-sans-arabic'],
    ];

    public const RADIUS_SCALE = ['soft' => 0.6, 'rounded' => 1.0, 'sharp' => 0.2];

    /** The stylesheet's radius tokens, in rem. */
    private const RADII = ['md' => 0.75, 'lg' => 1.0, 'xl' => 1.25, '2xl' => 1.75, '3xl' => 2.25];

    public static function for(Restaurant $restaurant, ?ThemeSpec $draft = null): string
    {
        if ($draft !== null) {
            return self::build($draft, $restaurant);
        }

        return Cache::remember(
            "theme-css:{$restaurant->id}:".MenuCache::version($restaurant->id),
            now()->addDay(),
            fn (): string => self::build(ThemeResolver::for($restaurant), $restaurant),
        );
    }

    /**
     * Catalogue fonts the theme adds beyond the stylesheet's own.
     *
     * @return list<string>
     */
    public static function fontKeys(ThemeSpec $spec, Restaurant $restaurant): array
    {
        $keys = [];

        foreach (self::changedFonts($spec) as $role => $pair) {
            $keys = [...$keys, ...array_values(FontCatalog::pair(self::catalogPair($spec, $restaurant, $role, $pair)))];
        }

        return array_values(array_unique($keys));
    }

    /** The body face to preload for a page in $locale, when the theme changes it. */
    public static function preload(ThemeSpec $spec, string $locale): ?string
    {
        if (! array_key_exists('body', self::changedFonts($spec)) || str_starts_with($spec->fonts['body'], 'brand:')) {
            return null;
        }

        $pair = FontCatalog::pair($spec->fonts['body'], 'body');

        return FontCatalog::preloadUrl($locale === 'ar' ? $pair['arabic'] : $pair['latin']);
    }

    private static function build(ThemeSpec $spec, Restaurant $restaurant): string
    {
        $tokens = ThemeTokens::for($spec);
        $dark = $spec->mode === 'dark';
        $colors = $spec->colors;
        $vars = ['brand' => $tokens['brand'], 'color-on-brand' => $tokens['color-on-brand'], 'color-on-accent' => $tokens['color-on-accent']];

        $groups = [
            'color-ink-' => $dark || $colors['text'] !== self::CLASSIC['colors']['text'],
            'color-ivory-' => $dark || $colors['background'] !== self::CLASSIC['colors']['background'],
            'color-brass-' => $colors['accent'] !== self::CLASSIC['colors']['accent'],
            'color-white' => $dark,
        ];

        foreach ($tokens as $name => $value) {
            foreach ($groups as $prefix => $changed) {
                if ($changed && str_starts_with($name, $prefix)) {
                    $vars[$name] = $value;
                }
            }
        }

        $faces = '';
        foreach (self::changedFonts($spec) as $role => $pair) {
            [$latin, $arabic] = self::stacks($spec, $restaurant, $role, $pair, $faces);
            $vars[$role === 'body' ? 'font-sans' : 'font-display'] = $latin;
            $vars[$role === 'body' ? 'font-sans-ar' : 'font-display-ar'] = $arabic;
        }

        $scale = self::RADIUS_SCALE[$spec->corners];
        $vars['radius-scale'] = rtrim(rtrim(number_format($scale, 2, '.', ''), '0'), '.');
        if ($scale !== 1.0) {
            foreach (self::RADII as $size => $rem) {
                $vars["radius-{$size}"] = round($rem * $scale, 3).'rem';
            }
        }

        $declarations = implode(' ', array_map(fn (string $name, string $value): string => "--{$name}: {$value};", array_keys($vars), $vars));

        return $faces.FontCatalog::fontFaceCss(self::fontKeys($spec, $restaurant)).":root { {$declarations} }";
    }

    /**
     * The Latin-first and Arabic-first stacks for a role. An uploaded font
     * leads, and the template's pair fills the script it doesn't cover.
     *
     * @return array{0: string, 1: string}
     */
    private static function stacks(ThemeSpec $spec, Restaurant $restaurant, string $role, string $pair, string &$faces): array
    {
        $font = str_starts_with($pair, 'brand:') ? BrandFonts::find($restaurant, $pair) : null;
        $fallback = self::catalogPair($spec, $restaurant, $role, $pair);

        if ($font === null) {
            return [FontCatalog::stack($fallback), FontCatalog::stack($fallback, arabicFirst: true)];
        }

        $faces .= BrandFonts::fontFace($font);
        $lead = "'{$font->family()}', ";

        return [$lead.FontCatalog::stack($fallback), $lead.FontCatalog::stack($fallback, arabicFirst: true)];
    }

    /** The catalogue pair for a role: the theme's own, or the template's when the theme uses an uploaded font. */
    private static function catalogPair(ThemeSpec $spec, Restaurant $restaurant, string $role, string $pair): string
    {
        if (! str_starts_with($pair, 'brand:')) {
            return $pair;
        }

        $template = ThemeResolver::template($restaurant)->spec()->fonts[$role];

        return str_starts_with($template, 'brand:') ? self::CLASSIC['fonts'][$role] : $template;
    }

    /**
     * @return array<string, string> Role => pair, for the fonts that differ from the stylesheet's.
     */
    private static function changedFonts(ThemeSpec $spec): array
    {
        return array_diff_assoc($spec->fonts, self::CLASSIC['fonts']);
    }
}
