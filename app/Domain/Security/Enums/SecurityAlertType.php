<?php

declare(strict_types=1);

namespace App\Domain\Security\Enums;

use Filament\Support\Contracts\HasLabel;

enum SecurityAlertType: string implements HasLabel
{
    case AccountLocked = 'account_locked';
    case IpLocked = 'ip_locked';

    public function getLabel(): string
    {
        return __("security.alerts.{$this->value}.title");
    }
}
