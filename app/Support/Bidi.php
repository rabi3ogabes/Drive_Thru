<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bidirectional-text helpers. Phone numbers, emails and codes are left-to-right
 * even inside Arabic pages; isolating them keeps "+974 5492 6330" from being
 * mirrored into "6330 5492 974+" while the surrounding layout stays RTL.
 */
final class Bidi
{
    private const LRI = "\u{2066}";

    private const PDI = "\u{2069}";

    public static function ltr(?string $value): ?string
    {
        return filled($value) ? self::LRI.$value.self::PDI : $value;
    }
}
