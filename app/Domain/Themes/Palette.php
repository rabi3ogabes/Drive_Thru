<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Themes\Exceptions\ThemeRejected;

/**
 * Builds a full shade scale (50…950) from one colour in OKLCH: the hue
 * stays, lightness follows a fixed curve, chroma eases off away from the
 * chosen colour and is pulled back into the sRGB gamut. Deterministic, so
 * the same colour always gives the same CSS.
 */
final class Palette
{
    /** Target OKLCH lightness per Tailwind step. */
    public const LIGHTNESS = [
        50 => 0.975, 100 => 0.95, 200 => 0.9, 300 => 0.83, 400 => 0.72, 500 => 0.6,
        600 => 0.5, 700 => 0.42, 800 => 0.34, 900 => 0.27, 950 => 0.2,
    ];

    public static function normalize(string $hex): string
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
            throw new ThemeRejected('bad_color', ['value' => mb_substr($hex, 0, 20)]);
        }

        return strtolower($hex);
    }

    /**
     * @param  list<int>  $steps
     * @return array<int, string>
     */
    public static function shades(string $hex, array $steps): array
    {
        [$lightness, $chroma, $hue] = self::oklch(self::normalize($hex));
        $shades = [];

        foreach ($steps as $step) {
            $target = self::LIGHTNESS[$step] ?? throw new ThemeRejected('bad_value', ['value' => (string) $step]);
            $c = $chroma * max(0.0, 1 - abs($target - $lightness) * 0.6);

            while (($rgb = self::fromOklch($target, $c, $hue)) === null) {
                $c = max(0.0, $c - 0.005);
            }

            $shades[$step] = $rgb;
        }

        return $shades;
    }

    /** The same colour, lighter (positive) or darker (negative) in OKLCH lightness, kept in gamut. */
    public static function shift(string $hex, float $delta): string
    {
        [$lightness, $chroma, $hue] = self::oklch(self::normalize($hex));

        if ($delta === 0.0) {
            return self::normalize($hex);
        }

        $target = min(0.995, max(0.02, $lightness + $delta));

        while (($rgb = self::fromOklch($target, $chroma, $hue)) === null) {
            $chroma = max(0.0, $chroma - 0.005);
        }

        return $rgb;
    }

    /**
     * @return array{0: float, 1: float, 2: float} Lightness 0-1, chroma, hue in degrees.
     */
    public static function oklch(string $hex): array
    {
        [$r, $g, $b] = array_map(fn (int $v): float => self::toLinear($v / 255), self::rgb($hex));

        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        $hue = rad2deg(atan2($bb, $a));

        return [$lightness, sqrt($a * $a + $bb * $bb), $hue < 0 ? $hue + 360 : $hue];
    }

    /** The colour as #rrggbb, or null when it falls outside sRGB. */
    private static function fromOklch(float $lightness, float $chroma, float $hue): ?string
    {
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $rgb = [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];

        foreach ($rgb as $channel) {
            if ($channel < -0.0001 || $channel > 1.0001) {
                return null;
            }
        }

        return '#'.implode('', array_map(fn (float $v): string => sprintf('%02x', (int) round(self::toSrgb(min(1.0, max(0.0, $v))) * 255)), $rgb));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = self::normalize($hex);

        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    public static function toLinear(float $channel): float
    {
        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }

    private static function toSrgb(float $channel): float
    {
        return $channel <= 0.0031308 ? $channel * 12.92 : 1.055 * $channel ** (1 / 2.4) - 0.055;
    }
}
