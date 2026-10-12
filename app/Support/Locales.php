<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Read access to config/locales.php.
 */
final class Locales
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_values(array_filter(array_keys(config()->array('locales.supported')), is_string(...)));
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::codes(), true);
    }

    public static function direction(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return config("locales.supported.{$locale}.dir", 'ltr');
    }

    public static function isRtl(?string $locale = null): bool
    {
        return self::direction($locale) === 'rtl';
    }

    public static function native(string $locale): string
    {
        return config("locales.supported.{$locale}.native", $locale);
    }

    /** The language a switcher should offer next (two-language product). */
    public static function other(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return collect(self::codes())->first(fn (string $code): bool => $code !== $locale) ?? $locale;
    }
}
