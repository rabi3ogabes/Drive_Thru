<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * Brand palettes for Filament, mirrored from resources/css/tokens.css.
 * Filament expects every shade from 50 to 950 in OKLCH.
 */
final class Palette
{
    /** @return array<int, string> */
    public static function teal(): array
    {
        return self::oklch([
            50 => '#eef6f5', 100 => '#d4e8e5', 200 => '#a9d1cb', 300 => '#75b2aa', 400 => '#469189',
            500 => '#2b736c', 600 => '#1e5d57', 700 => '#184c47', 800 => '#143f3b', 900 => '#0f3b3a', 950 => '#082423',
        ]);
    }

    /** @return array<int, string> */
    public static function ink(): array
    {
        return self::oklch([
            50 => '#f5f7fa', 100 => '#eceff4', 200 => '#d9dee7', 300 => '#bcc4d2', 400 => '#8b95a8',
            500 => '#636d82', 600 => '#475167', 700 => '#313a4f', 800 => '#1c2436', 900 => '#111827', 950 => '#0a0f1c',
        ]);
    }

    /** @return array<int, string> */
    public static function brass(): array
    {
        return self::oklch([
            50 => '#faf6ef', 100 => '#f2e8d6', 200 => '#e5d1ae', 300 => '#d5b582', 400 => '#c39e66',
            500 => '#b08d57', 600 => '#957244', 700 => '#775a37', 800 => '#5e472f', 900 => '#4c3a28', 950 => '#2e2318',
        ]);
    }

    /**
     * @param  array<int, string>  $hexes
     * @return array<int, string>
     */
    private static function oklch(array $hexes): array
    {
        return array_map(fn (string $hex): string => Color::convertToOklch($hex), $hexes);
    }
}
