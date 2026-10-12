<?php

declare(strict_types=1);

namespace App\Domain\Customers\Enums;

use Filament\Support\Contracts\HasLabel;

enum VehicleType: string implements HasLabel
{
    case Car = 'car';
    case Suv = 'suv';
    case Pickup = 'pickup';
    case Van = 'van';
    case Motorbike = 'motorbike';

    public function getLabel(): string
    {
        return __("customers.vehicle_type.{$this->value}");
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Car => '🚗',
            self::Suv => '🚙',
            self::Pickup => '🛻',
            self::Van => '🚐',
            self::Motorbike => '🏍️',
        };
    }
}
