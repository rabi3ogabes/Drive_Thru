<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Groups of guests, recomputed every night from picked-up orders. */
enum Segment: string implements HasColor, HasLabel
{
    case New = 'new';
    case Regular = 'regular';
    case Vip = 'vip';
    case HighSpender = 'high_spender';
    case AtRisk = 'at_risk';
    case Inactive = 'inactive';
    case FrequentBuyer = 'frequent_buyer';

    public function getLabel(): string
    {
        return Lang::text('crm.segments.'.$this->value);
    }

    public function description(): string
    {
        return Lang::text('crm.segment_hints.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vip, self::HighSpender => 'warning',
            self::New, self::Regular, self::FrequentBuyer => 'success',
            self::AtRisk => 'danger',
            self::Inactive => 'gray',
        };
    }
}
