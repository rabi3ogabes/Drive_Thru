<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum PromotionType: string implements HasDescription, HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case Bogo = 'bogo';
    case FreeItem = 'free_item';
    case ComboPrice = 'combo_price';
    case BonusPoints = 'bonus_points';

    public function getLabel(): string
    {
        return Lang::text('promotions.types.'.$this->value.'.label');
    }

    public function getDescription(): string
    {
        return Lang::text('promotions.types.'.$this->value.'.description');
    }
}
