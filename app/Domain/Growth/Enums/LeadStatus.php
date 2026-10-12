<?php

declare(strict_types=1);

namespace App\Domain\Growth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The sales pipeline, in order. Won and Lost are terminal.
 */
enum LeadStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case DemoBooked = 'demo_booked';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return __("leads.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Contacted => 'warning',
            self::DemoBooked => 'info',
            self::Won => 'success',
            self::Lost => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::New => 'heroicon-m-sparkles',
            self::Contacted => 'heroicon-m-chat-bubble-left-right',
            self::DemoBooked => 'heroicon-m-calendar-days',
            self::Won => 'heroicon-m-trophy',
            self::Lost => 'heroicon-m-archive-box',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::New, self::Contacted, self::DemoBooked];
    }
}
