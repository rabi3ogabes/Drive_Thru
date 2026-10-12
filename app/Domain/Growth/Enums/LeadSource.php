<?php

declare(strict_types=1);

namespace App\Domain\Growth\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadSource: string implements HasLabel
{
    case Website = 'website';
    case Manual = 'manual';
    case Referral = 'referral';
    case Phone = 'phone';
    case Event = 'event';

    public function getLabel(): string
    {
        return __("leads.source.{$this->value}");
    }
}
