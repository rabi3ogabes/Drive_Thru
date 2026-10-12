<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasLabel;

/** Why points moved. Earn, Bonus, Returned and positive Adjust create lots that can expire. */
enum LedgerType: string implements HasLabel
{
    case Earn = 'earn';
    case Bonus = 'bonus';
    case Redeem = 'redeem';
    case Reverse = 'reverse';
    case Returned = 'returned';
    case Expire = 'expire';
    case Adjust = 'adjust';

    public function getLabel(): string
    {
        return Lang::text('loyalty.ledger.'.$this->value);
    }

    /** Counts towards tiers (points earned by spending, not given back or adjusted). */
    public function qualifies(): bool
    {
        return in_array($this, [self::Earn, self::Bonus, self::Reverse], true);
    }
}
