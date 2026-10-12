<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\App\Workspace;
use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * A price field: shown and typed in major units of the restaurant's currency
 * (25.50 QAR), stored as integer minor units (2550). Never a float in the DB.
 */
final class MoneyInput
{
    public static function make(string $name, bool $allowNegative = false): TextInput
    {
        $currency = fn (): string => Workspace::restaurant()->currency;

        return TextInput::make($name)
            ->numeric()
            ->inputMode('decimal')
            ->step(fn (): float => 1 / 10 ** Money::decimals($currency()))
            ->minValue($allowNegative ? null : 0)
            ->suffix($currency)
            ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state)
                ? number_format(((int) $state) / 10 ** Money::decimals($currency()), Money::decimals($currency()), '.', '')
                : null)
            ->dehydrateStateUsing(fn (mixed $state): ?int => filled($state) && is_numeric($state)
                ? Money::toMinor((string) $state, $currency())
                : null)
            ->extraInputAttributes(['dir' => 'ltr']);
    }
}
