<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The product shows and stores Western digits (0-9) in every language, so
 * prices, phones, plates and codes read the same everywhere. Arabic-Indic
 * and Persian digits typed on Arabic keyboards are converted on input.
 */
final class Digits
{
    private const EASTERN = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٫', '٬'];

    private const WESTERN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', ','];

    public static function toLatin(string $value): string
    {
        return str_replace(self::EASTERN, self::WESTERN, $value);
    }

    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, '.', ',');
    }
}
