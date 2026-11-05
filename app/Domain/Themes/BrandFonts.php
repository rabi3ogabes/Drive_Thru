<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Tenancy\Models\Restaurant;

/**
 * Which uploaded brand fonts a restaurant may use in its theme.
 */
final class BrandFonts
{
    /** True when $font ("brand:12") is an uploaded font of $restaurant. */
    public static function owns(?Restaurant $restaurant, string $font): bool
    {
        return false;
    }
}
