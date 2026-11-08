<?php

declare(strict_types=1);

namespace App\Domain\Themes;

/**
 * WCAG 2.2 contrast for themes: text must read on the background (4.5:1)
 * and muted text too (3:1). Button text on the brand colour is chosen for
 * the owner; when even the better choice is weak, it's a warning.
 */
final class Contrast
{
    public const TEXT = 4.5;

    public const MUTED = 3.0;

    public static function ratio(string $a, string $b): float
    {
        [$high, $low] = [self::luminance($a), self::luminance($b)];

        if ($low > $high) {
            [$high, $low] = [$low, $high];
        }

        return ($high + 0.05) / ($low + 0.05);
    }

    /** White or the theme's text colour, whichever reads better on the background. */
    public static function onColor(string $background, string $text): string
    {
        return self::ratio('#ffffff', $background) >= self::ratio($text, $background) ? '#ffffff' : Palette::normalize($text);
    }

    /**
     * @return array{ok: bool, errors: list<array{key: string, ratio: float}>, warnings: list<array{key: string, ratio: float}>}
     */
    public static function check(ThemeSpec $spec): array
    {
        $colors = $spec->colors;
        $errors = [];
        $warnings = [];

        $text = self::ratio($colors['text'], $colors['background']);
        if ($text < self::TEXT) {
            $errors[] = ['key' => 'text_on_background', 'ratio' => round($text, 2)];
        }

        $muted = self::ratio(ThemeTokens::muted($spec), $colors['background']);
        if ($muted < self::MUTED) {
            $errors[] = ['key' => 'muted_on_background', 'ratio' => round($muted, 2)];
        }

        $button = self::ratio(ThemeTokens::onBrand($spec), $colors['brand']);
        if ($button < self::TEXT) {
            $warnings[] = ['key' => 'button_text', 'ratio' => round($button, 2)];
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(fn (int $v): float => Palette::toLinear($v / 255), Palette::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
