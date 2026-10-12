<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RestaurantStatus: string implements HasColor, HasLabel
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("tenancy.restaurant_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Cancelled => 'gray',
        };
    }

    /** Whether guests may place orders and staff may operate. */
    public function isOperational(): bool
    {
        return $this === self::Trial || $this === self::Active;
    }
}
