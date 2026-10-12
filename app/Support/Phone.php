<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phone numbers in E.164 (+97455551234). Accepts what guests actually type:
 * Arabic-Indic digits, spaces, dashes, 00 prefixes and local 8-digit Qatari
 * numbers (which get +974).
 */
final class Phone
{
    public const DEFAULT_COUNTRY_CODE = '974';

    public static function normalize(string $input, string $countryCode = self::DEFAULT_COUNTRY_CODE): ?string
    {
        $value = Digits::toLatin(trim($input));
        $plus = str_starts_with($value, '+');
        $digits = (string) preg_replace('/\D/', '', $value);

        if ($digits === '') {
            return null;
        }

        if (! $plus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $plus = true;
        }

        if (! $plus) {
            $digits = match (true) {
                strlen($digits) === 8 => $countryCode.$digits,
                str_starts_with($digits, $countryCode) && strlen($digits) === strlen($countryCode) + 8 => $digits,
                default => $countryCode.ltrim($digits, '0'),
            };
        }

        $length = strlen($digits);

        return $length >= 8 && $length <= 15 ? '+'.$digits : null;
    }

    /** "+974 5555 1234" for display. */
    public static function format(string $e164): string
    {
        if (str_starts_with($e164, '+974') && strlen($e164) === 12) {
            return '+974 '.substr($e164, 4, 4).' '.substr($e164, 8);
        }

        return $e164;
    }
}
