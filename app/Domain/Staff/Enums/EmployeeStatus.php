<?php

declare(strict_types=1);

namespace App\Domain\Staff\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmployeeStatus: string implements HasColor, HasLabel
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return __("staff.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Invited => 'info',
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }
}
