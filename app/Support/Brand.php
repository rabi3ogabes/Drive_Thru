<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The platform's name: ذوق in Arabic, Dhawq in every other language. Use it
 * wherever a person sees the platform's name, never config('app.name') or a
 * literal, so the brand can't drift. BrandNameTest fails on the old name.
 */
final class Brand
{
    public const ARABIC = 'ذوق';

    public const LATIN = 'Dhawq';

    public static function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? self::ARABIC : self::LATIN;
    }

    /** Both names, for places that speak both languages at once (invoices, posters). */
    public static function full(): string
    {
        return self::ARABIC.' · '.self::LATIN;
    }

    public static function latin(): string
    {
        return self::LATIN;
    }
}
