<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Themes\Exceptions\ThemeRejected;

/**
 * The fonts a theme can use: 8 Latin and 8 Arabic faces, self-hosted under
 * public/fonts/guest (copied from @fontsource by `npm run fonts`). A theme
 * picks pairs, "latin+arabic"; each face carries its script's unicode
 * range, so a page downloads only the faces its text needs.
 *
 * The list lives in resources/fonts/guest-fonts.json, shared with the copy script.
 */
final class FontCatalog
{
    public const LATIN_RANGE = 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD';

    public const ARABIC_RANGE = 'U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC';

    private const FALLBACK = [
        'sans' => 'ui-sans-serif, system-ui, sans-serif',
        'serif' => 'ui-serif, Georgia, serif',
    ];

    /** @var array<string, array{name: string, package: string, script: 'latin'|'arabic', kind: 'sans'|'serif', role: 'heading'|'both', faces: list<array{file: string, weight: string}>}>|null */
    private static ?array $fonts = null;

    /**
     * @return array<string, array{name: string, package: string, script: 'latin'|'arabic', kind: 'sans'|'serif', role: 'heading'|'both', faces: list<array{file: string, weight: string}>}>
     */
    public static function all(): array
    {
        if (self::$fonts === null) {
            /** @var array<string, array{name: string, package: string, script: 'latin'|'arabic', kind: 'sans'|'serif', role: 'heading'|'both', faces: list<array{file: string, weight: string}>}> $fonts */
            $fonts = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fonts/guest-fonts.json'), true, 8, JSON_THROW_ON_ERROR);
            self::$fonts = $fonts;
        }

        return self::$fonts;
    }

    /**
     * @return array{latin: string, arabic: string}
     */
    public static function pair(string $pair, string $role = 'heading'): array
    {
        $parts = explode('+', $pair);
        $fonts = self::all();

        if (count($parts) !== 2
            || ($fonts[$parts[0]]['script'] ?? null) !== 'latin'
            || ($fonts[$parts[1]]['script'] ?? null) !== 'arabic'
            || ($role === 'body' && ($fonts[$parts[0]]['role'] !== 'both' || $fonts[$parts[1]]['role'] !== 'both'))) {
            throw new ThemeRejected('unknown_font', ['role' => $role]);
        }

        return ['latin' => $parts[0], 'arabic' => $parts[1]];
    }

    /** The CSS font-family stack for a pair: Latin face, Arabic face, then a system fallback. */
    public static function stack(string $pair, bool $arabicFirst = false): string
    {
        ['latin' => $latin, 'arabic' => $arabic] = self::pair($pair);
        $faces = $arabicFirst ? "'dt-{$arabic}', 'dt-{$latin}'" : "'dt-{$latin}', 'dt-{$arabic}'";

        return $faces.', '.self::FALLBACK[self::all()[$latin]['kind']];
    }

    /**
     * @param  list<string>  $keys
     */
    public static function fontFaceCss(array $keys): string
    {
        $fonts = self::all();
        $css = '';

        foreach (array_unique($keys) as $key) {
            $font = $fonts[$key] ?? throw new ThemeRejected('unknown_font', ['role' => $key]);
            $range = $font['script'] === 'arabic' ? self::ARABIC_RANGE : self::LATIN_RANGE;

            foreach ($font['faces'] as $face) {
                $css .= "@font-face { font-family: 'dt-{$key}'; font-style: normal; font-weight: {$face['weight']}; font-display: swap; "
                    .'src: url('.self::url($key, $face['file']).") format('woff2'); unicode-range: {$range}; }\n";
            }
        }

        return $css;
    }

    /** The first face of a font, to preload the body text. */
    public static function preloadUrl(string $key): string
    {
        $font = self::all()[$key] ?? throw new ThemeRejected('unknown_font', ['role' => $key]);

        return self::url($key, $font['faces'][0]['file']);
    }

    private static function url(string $key, string $file): string
    {
        return asset("fonts/guest/{$key}/{$file}");
    }
}
