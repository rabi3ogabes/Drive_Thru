<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

use Filament\Support\Contracts\HasLabel;

enum AnnouncementAudience: string implements HasLabel
{
    case Everyone = 'everyone';
    case Owners = 'owners';

    public function getLabel(): string
    {
        return __("platform.announcement_audience.{$this->value}");
    }
}
