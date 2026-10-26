<?php

declare(strict_types=1);

namespace App\Domain\Services\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What Drive-Thru Plus sells. A restaurant takes one, two or all three; each
 * can run as a free demo first. Plans only set limits and add-ons.
 */
enum Service: string implements HasDescription, HasIcon, HasLabel
{
    case DriveThru = 'drive_thru';
    case ReadyCall = 'ready_call';
    case Rewards = 'rewards';

    public function getLabel(): string
    {
        return Lang::text('services.names.'.$this->value);
    }

    public function getDescription(): string
    {
        return Lang::text('services.descriptions.'.$this->value);
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DriveThru => 'heroicon-o-truck',
            self::ReadyCall => 'heroicon-o-bell-alert',
            self::Rewards => 'heroicon-o-gift',
        };
    }

    /** URL segment on the public website. */
    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(str_replace('-', '_', $slug));
    }
}
