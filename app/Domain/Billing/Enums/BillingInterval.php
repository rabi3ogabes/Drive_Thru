<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;

enum BillingInterval: string implements HasLabel
{
    case Monthly = 'month';
    case Yearly = 'year';

    public function getLabel(): string
    {
        return __("billing.interval.{$this->value}");
    }

    public function addTo(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $date->addMonthNoOverflow(),
            self::Yearly => $date->addYearNoOverflow(),
        };
    }

    /** Months one period covers, used to normalise revenue to MRR. */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }
}
