<?php

declare(strict_types=1);

namespace App\Domain\Branches\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** What a paired screen is used for in the branch. */
enum DeviceKind: string implements HasDescription, HasIcon, HasLabel
{
    case Kitchen = 'kds';
    case Cashier = 'cashier';
    case Pickup = 'pickup';
    case Tv = 'tv';
    case Pager = 'pager';
    case ReadyBoard = 'ready_board';

    public function getLabel(): string
    {
        return __("devices.kind.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("devices.kind.{$this->value}.description");
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Kitchen => 'heroicon-o-fire',
            self::Cashier => 'heroicon-o-banknotes',
            self::Pickup => 'heroicon-o-shopping-bag',
            self::Tv => 'heroicon-o-tv',
            self::Pager => 'heroicon-o-bell-alert',
            self::ReadyBoard => 'heroicon-o-queue-list',
        };
    }
}
