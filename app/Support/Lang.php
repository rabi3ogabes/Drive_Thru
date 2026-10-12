<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Typed translation for places that need a string. `__()` can return an
 * array when a key points to a group; that is always a bug in these spots,
 * so the key itself is returned to make it visible.
 */
final class Lang
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $value = __($key, $replace, $locale);

        return is_string($value) ? $value : $key;
    }
}
