<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer minor units (dirhams, fils, cents) and never as
 * floats. This class converts and formats it, always with Western digits.
 */
final class Money
{
    /** Minor-unit exponent per ISO 4217 code (Gulf currencies with 3 decimals included). */
    private const DECIMALS = ['KWD' => 3, 'BHD' => 3, 'OMR' => 3, 'JOD' => 3];

    /** @var array<string, array{ar: string, en: string}> */
    private const SYMBOLS = [
        'QAR' => ['ar' => 'ر.ق', 'en' => 'QAR'],
        'SAR' => ['ar' => 'ر.س', 'en' => 'SAR'],
        'AED' => ['ar' => 'د.إ', 'en' => 'AED'],
        'KWD' => ['ar' => 'د.ك', 'en' => 'KWD'],
        'BHD' => ['ar' => 'د.ب', 'en' => 'BHD'],
        'OMR' => ['ar' => 'ر.ع', 'en' => 'OMR'],
        'JOD' => ['ar' => 'د.أ', 'en' => 'JOD'],
        'USD' => ['ar' => 'دولار', 'en' => 'USD'],
    ];

    /** @return list<string> */
    public static function currencies(): array
    {
        return array_keys(self::SYMBOLS);
    }

    public static function decimals(string $currency): int
    {
        return self::DECIMALS[strtoupper($currency)] ?? 2;
    }

    /**
     * Converts a major amount typed by a person ("349", "349.5", "٣٤٩٫٥") to minor units.
     */
    public static function toMinor(string|int|float $major, string $currency): int
    {
        $normalized = str_replace([',', ' '], '', Digits::toLatin((string) $major));

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("Not an amount: {$major}");
        }

        return (int) round(((float) $normalized) * (10 ** self::decimals($currency)));
    }

    public static function toMajor(int $minor, string $currency): string
    {
        $decimals = self::decimals($currency);

        return number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    public static function symbol(string $currency, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $currency = strtoupper($currency);

        return self::SYMBOLS[$currency][$locale === 'ar' ? 'ar' : 'en'] ?? $currency;
    }

    /**
     * "349.00 QAR" in English, "349.00 ر.ق" in Arabic. Western digits in both.
     */
    public static function format(int $minor, string $currency, ?string $locale = null): string
    {
        $decimals = self::decimals($currency);
        $amount = number_format($minor / (10 ** $decimals), $decimals, '.', ',');

        return $amount."\u{00A0}".self::symbol($currency, $locale);
    }

    /**
     * Applies a rate in basis points (500 = 5%) with half-up rounding.
     */
    public static function percentOf(int $minor, int $basisPoints): int
    {
        return intdiv($minor * $basisPoints + 5000, 10000);
    }
}
