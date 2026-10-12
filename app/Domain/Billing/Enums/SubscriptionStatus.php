<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("billing.subscription_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trialing => 'info',
            self::Active => 'success',
            self::PastDue => 'warning',
            self::Suspended => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Trialing => 'heroicon-m-sparkles',
            self::Active => 'heroicon-m-check-badge',
            self::PastDue => 'heroicon-m-clock',
            self::Suspended => 'heroicon-m-pause-circle',
            self::Cancelled => 'heroicon-m-x-circle',
        };
    }

    /** Statuses that keep the restaurant running (past due is inside its grace period). */
    public function isLive(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }
}
