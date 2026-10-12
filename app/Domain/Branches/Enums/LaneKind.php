<?php

declare(strict_types=1);

namespace App\Domain\Branches\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** Where a customer waits: a drive-thru lane or a numbered curbside spot. */
enum LaneKind: string implements HasIcon, HasLabel
{
    case Lane = 'lane';
    case Curbside = 'curbside';

    public function getLabel(): string
    {
        return __("branches.lane_kind.{$this->value}");
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Lane => 'heroicon-o-arrows-right-left',
            self::Curbside => 'heroicon-o-map-pin',
        };
    }
}
