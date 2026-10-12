<?php

declare(strict_types=1);

namespace App\Domain\Menu\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** A single item, or a combo (meal) built from slots of other items. */
enum ProductType: string implements HasIcon, HasLabel
{
    case Item = 'item';
    case Combo = 'combo';

    public function getLabel(): string
    {
        return __("menu.product_type.{$this->value}");
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Item => 'heroicon-o-cube',
            self::Combo => 'heroicon-o-squares-plus',
        };
    }
}
