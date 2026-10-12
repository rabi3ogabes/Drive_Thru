<?php

declare(strict_types=1);

namespace App\Domain\Branches\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What a branch is doing right now, from its toggle, pause, busy mode,
 * closures and weekly hours (in that order of precedence).
 */
enum BranchState: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case Busy = 'busy';
    case Paused = 'paused';
    case Closed = 'closed';
    case Holiday = 'holiday';
    case Disabled = 'disabled';

    public function getLabel(): string
    {
        return __("branches.state.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Busy => 'warning',
            self::Paused => 'danger',
            self::Closed, self::Holiday, self::Disabled => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-m-check-circle',
            self::Busy => 'heroicon-m-clock',
            self::Paused => 'heroicon-m-pause-circle',
            self::Closed => 'heroicon-m-moon',
            self::Holiday => 'heroicon-m-calendar-days',
            self::Disabled => 'heroicon-m-no-symbol',
        };
    }

    /** Whether new orders can be placed for as-soon-as-possible pickup. */
    public function acceptsOrders(): bool
    {
        return $this === self::Open || $this === self::Busy;
    }
}
