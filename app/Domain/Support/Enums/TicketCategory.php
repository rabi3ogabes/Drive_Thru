<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasLabel;

/** What went wrong with the order, in the guest's words. */
enum TicketCategory: string implements HasLabel
{
    case MissingItem = 'missing_item';
    case WrongItem = 'wrong_item';
    case Quality = 'quality';
    case ChargedIncorrectly = 'charged_incorrectly';
    case Other = 'other';

    public function getLabel(): string
    {
        return Lang::text('support.categories.'.$this->value);
    }

    /** The guest picks the order lines that were missing or wrong. */
    public function asksForItems(): bool
    {
        return in_array($this, [self::MissingItem, self::WrongItem], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::MissingItem => 'heroicon-o-archive-box-x-mark',
            self::WrongItem => 'heroicon-o-arrows-right-left',
            self::Quality => 'heroicon-o-fire',
            self::ChargedIncorrectly => 'heroicon-o-credit-card',
            self::Other => 'heroicon-o-chat-bubble-left-ellipsis',
        };
    }
}
