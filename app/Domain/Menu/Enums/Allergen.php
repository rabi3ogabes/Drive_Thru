<?php

declare(strict_types=1);

namespace App\Domain\Menu\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The common food allergens (GCC / EU labelling), each with an icon so guests
 * can scan a menu at a glance.
 */
enum Allergen: string implements HasIcon, HasLabel
{
    case Gluten = 'gluten';
    case Dairy = 'dairy';
    case Eggs = 'eggs';
    case Nuts = 'nuts';
    case Peanuts = 'peanuts';
    case Soy = 'soy';
    case Fish = 'fish';
    case Shellfish = 'shellfish';
    case Sesame = 'sesame';
    case Mustard = 'mustard';
    case Celery = 'celery';

    public function getLabel(): string
    {
        return __("menu.allergens.{$this->value}");
    }

    /** A short emoji used on guest-facing menus and printed tickets. */
    public function symbol(): string
    {
        return match ($this) {
            self::Gluten => '🌾',
            self::Dairy => '🥛',
            self::Eggs => '🥚',
            self::Nuts => '🌰',
            self::Peanuts => '🥜',
            self::Soy => '🫘',
            self::Fish => '🐟',
            self::Shellfish => '🦐',
            self::Sesame => '⚪',
            self::Mustard => '🟡',
            self::Celery => '🥬',
        };
    }

    public function getIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }
}
