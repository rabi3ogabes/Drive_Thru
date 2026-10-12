<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';

    public function getLabel(): string
    {
        return __("billing.invoice_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Paid => 'success',
            self::Void => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-m-document-text',
            self::Paid => 'heroicon-m-check-circle',
            self::Void => 'heroicon-m-no-symbol',
        };
    }
}
