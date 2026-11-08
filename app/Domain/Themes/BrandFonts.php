<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Models\BrandFont;
use Illuminate\Support\Facades\Storage;

/**
 * Uploaded brand fonts in themes: which ones a restaurant may use, and the
 * font-face rule that serves them from our own storage. A font only covers
 * the scripts the owner chose; the template's font fills in the rest.
 */
final class BrandFonts
{
    /** True when $font ("brand:12") is an uploaded font of $restaurant. */
    public static function owns(?Restaurant $restaurant, string $font): bool
    {
        return self::find($restaurant, $font) !== null;
    }

    public static function find(?Restaurant $restaurant, string $font): ?BrandFont
    {
        if ($restaurant === null || preg_match('/^brand:([1-9][0-9]{0,9})$/', $font, $match) !== 1) {
            return null;
        }

        return BrandFont::withoutTenancy()->where('restaurant_id', $restaurant->id)->whereKey((int) $match[1])->first();
    }

    public static function fontFace(BrandFont $font): string
    {
        $range = match ($font->scripts) {
            'latin' => ' unicode-range: '.FontCatalog::LATIN_RANGE.';',
            'arabic' => ' unicode-range: '.FontCatalog::ARABIC_RANGE.';',
            default => '',
        };

        return "@font-face { font-family: '{$font->family()}'; font-style: normal; font-weight: 100 900; font-display: swap; "
            .'src: url('.Storage::disk('public')->url($font->path).") format('woff2');{$range} }\n";
    }
}
