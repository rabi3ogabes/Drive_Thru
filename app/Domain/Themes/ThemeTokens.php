<?php

declare(strict_types=1);

namespace App\Domain\Themes;

/**
 * The design tokens a theme sets on guest pages, as CSS custom property
 * name (without "--") => #rrggbb. Guest views already draw from these
 * tokens (ink text, ivory backgrounds, white surfaces, brass accents,
 * --brand), so overriding them restyles every page.
 *
 * Dark themes mirror the ink scale, so "ink-900" stays the main text
 * colour and "ivory-50" stays the page background in both modes.
 */
final class ThemeTokens
{
    public const STEPS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /** How far each ivory step moves from the background, in OKLCH lightness. */
    private const IVORY = [50 => 0.0, 100 => 0.025, 200 => 0.055, 300 => 0.1];

    /**
     * @return array<string, string>
     */
    public static function for(ThemeSpec $spec): array
    {
        $dark = $spec->mode === 'dark';
        $colors = $spec->colors;
        $tokens = [];

        $ink = Palette::shades($colors['text'], self::STEPS);
        foreach (self::STEPS as $step) {
            $tokens["color-ink-{$step}"] = $ink[$dark ? self::mirror($step) : $step];
        }
        $tokens['color-ink-900'] = $colors['text'];

        foreach (self::IVORY as $step => $delta) {
            $tokens["color-ivory-{$step}"] = Palette::shift($colors['background'], $dark ? $delta : -$delta);
        }

        // Cards and sheets: white on light themes, a lifted surface on dark ones.
        $tokens['color-white'] = $dark ? Palette::shift($colors['background'], 0.06) : '#ffffff';

        foreach (Palette::shades($colors['accent'], self::STEPS) as $step => $hex) {
            $tokens["color-brass-{$step}"] = $hex;
        }
        $tokens['color-brass-500'] = $colors['accent'];

        $tokens['brand'] = $colors['brand'];
        $tokens['color-on-brand'] = self::onBrand($spec);

        return $tokens;
    }

    /** Text on brand-coloured buttons: white, or the theme's darkest colour when that reads better. */
    public static function onBrand(ThemeSpec $spec): string
    {
        $colors = $spec->colors;

        return Contrast::onColor($colors['brand'], $spec->mode === 'dark' ? $colors['background'] : $colors['text']);
    }

    /** Secondary text (ink-500), which must still read on the background. */
    public static function muted(ThemeSpec $spec): string
    {
        return Palette::shades($spec->colors['text'], [500])[500];
    }

    private static function mirror(int $step): int
    {
        $index = array_search($step, self::STEPS, true);

        return self::STEPS[count(self::STEPS) - 1 - (int) $index];
    }
}
