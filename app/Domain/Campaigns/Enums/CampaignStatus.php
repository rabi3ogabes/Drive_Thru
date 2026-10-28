<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * draft → scheduled → sending → sent. A draft or scheduled campaign can be
 * cancelled; one that is sending can be stopped (cancelled) too.
 */
enum CampaignStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return Lang::text('campaigns.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Sending => 'warning',
            self::Sent => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-m-pencil-square',
            self::Scheduled => 'heroicon-m-clock',
            self::Sending => 'heroicon-m-paper-airplane',
            self::Sent => 'heroicon-m-check-circle',
            self::Cancelled => 'heroicon-m-x-circle',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Sending], true);
    }
}
