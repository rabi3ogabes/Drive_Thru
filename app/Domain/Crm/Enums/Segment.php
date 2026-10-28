<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Domain\Crm\CrmSettings;
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

    /** What puts a guest in this segment, with the restaurant's own thresholds. */
    public function description(?CrmSettings $settings = null): string
    {
        $values = ($settings ?? CrmSettings::fromDefaults())->all();

        return Lang::text('crm.segment_hints.'.$this->value, match ($this) {
            self::New => ['days' => $values['new_days']],
            self::Regular => ['orders' => $values['regular_orders']],
            self::Vip => ['percent' => $values['vip_top_percent']],
            self::HighSpender => ['factor' => $values['high_spender_factor']],
            self::AtRisk => ['days' => $values['at_risk_days']],
            self::Inactive => ['days' => $values['inactive_days']],
            self::FrequentBuyer => ['orders' => $values['frequent_orders']],
        });
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
