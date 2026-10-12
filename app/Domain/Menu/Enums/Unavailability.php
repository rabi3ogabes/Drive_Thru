<?php

declare(strict_types=1);

namespace App\Domain\Menu\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Why a product can't be ordered at a branch right now. */
enum Unavailability: string implements HasColor, HasLabel
{
    /** Switched off on the menu for every branch. */
    case Disabled = 'disabled';

    /** Its section is hidden. */
    case Hidden = 'hidden';

    /** This branch doesn't serve it. */
    case NotServedHere = 'not_served';

    /** Marked out of stock until the branch's next midnight. */
    case OutOfStockToday = 'out_of_stock';

    /** Today's stock count is used up. */
    case SoldOut = 'sold_out';

    /** Outside its menu schedule (e.g. breakfast). */
    case NotNow = 'not_now';

    public function getLabel(): string
    {
        return __("menu.unavailable.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OutOfStockToday, self::SoldOut => 'danger',
            self::NotNow => 'info',
            default => 'gray',
        };
    }

    /** Guests still see it (greyed with a reason) instead of it vanishing. */
    public function isShownToGuests(): bool
    {
        return in_array($this, [self::OutOfStockToday, self::SoldOut, self::NotNow], true);
    }
}
