<?php

declare(strict_types=1);

namespace App\Domain\Branches\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * A kitchen station. Items name the station that makes them; a station
 * screen shows only its own items, and an order is ready when every station
 * has finished. Items without a station go to the general station.
 */
enum StationKind: string implements HasIcon, HasLabel
{
    case General = 'general';
    case Grill = 'grill';
    case Fryer = 'fryer';
    case Cold = 'cold';
    case Drinks = 'drinks';
    case Desserts = 'desserts';

    public function getLabel(): string
    {
        return __("branches.station_kind.{$this->value}");
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::General => 'heroicon-o-squares-2x2',
            self::Grill => 'heroicon-o-fire',
            self::Fryer => 'heroicon-o-fire',
            self::Cold => 'heroicon-o-sparkles',
            self::Drinks => 'heroicon-o-beaker',
            self::Desserts => 'heroicon-o-cake',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::General => '🍽️',
            self::Grill => '🔥',
            self::Fryer => '🍟',
            self::Cold => '🥗',
            self::Drinks => '🥤',
            self::Desserts => '🍰',
        };
    }
}
