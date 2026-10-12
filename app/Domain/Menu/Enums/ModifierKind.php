<?php

declare(strict_types=1);

namespace App\Domain\Menu\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * "Choice" groups add or pick options (size, sauce, extras); "remove" groups
 * list ingredients a guest can leave out (no onions), always free.
 */
enum ModifierKind: string implements HasDescription, HasLabel
{
    case Choice = 'choice';
    case Remove = 'remove';

    public function getLabel(): string
    {
        return __("menu.modifier_kind.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("menu.modifier_kind.{$this->value}.description");
    }
}
