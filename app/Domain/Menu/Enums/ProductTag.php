<?php

declare(strict_types=1);

namespace App\Domain\Menu\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Dietary and style tags guests can filter by. */
enum ProductTag: string implements HasColor, HasLabel
{
    case Spicy = 'spicy';
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Kids = 'kids';
    case HighProtein = 'high_protein';
    case GlutenFree = 'gluten_free';
    case Healthy = 'healthy';

    public function getLabel(): string
    {
        return __("menu.tags.{$this->value}");
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Spicy => '🌶️',
            self::Vegetarian => '🥗',
            self::Vegan => '🌱',
            self::Kids => '🧒',
            self::HighProtein => '💪',
            self::GlutenFree => '🚫🌾',
            self::Healthy => '💚',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Spicy => 'danger',
            self::Vegetarian, self::Vegan, self::Healthy, self::GlutenFree => 'success',
            self::Kids => 'info',
            self::HighProtein => 'warning',
        };
    }
}
