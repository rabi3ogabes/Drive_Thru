<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasLabel;

/** What a reward gives. Bonus points are granted (birthday, referral…), never bought with points. */
enum RewardType: string implements HasLabel
{
    case FreeItem = 'free_item';
    case PercentOff = 'percent_off';
    case FixedOff = 'fixed_off';
    case BonusPoints = 'bonus_points';

    public function getLabel(): string
    {
        return Lang::text('loyalty.reward_types.'.$this->value);
    }
}
