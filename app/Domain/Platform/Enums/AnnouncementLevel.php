<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AnnouncementLevel: string implements HasColor, HasIcon, HasLabel
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return __("platform.announcement_level.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Success => 'success',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Info => 'heroicon-o-megaphone',
            self::Success => 'heroicon-o-sparkles',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-shield-exclamation',
        };
    }
}
