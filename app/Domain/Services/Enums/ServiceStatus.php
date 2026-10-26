<?php

declare(strict_types=1);

namespace App\Domain\Services\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * demo → active (kept after the demo, or added straight away) → cancelling
 * (works until the paid period ends) → ended. A demo nobody kept ends too.
 */
enum ServiceStatus: string implements HasColor, HasLabel
{
    case Demo = 'demo';
    case Active = 'active';
    case Cancelling = 'cancelling';
    case Ended = 'ended';

    public function getLabel(): string
    {
        return Lang::text('services.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Demo => 'info',
            self::Active => 'success',
            self::Cancelling => 'warning',
            self::Ended => 'gray',
        };
    }

    /** Whether the restaurant can use the service right now. */
    public function isOn(): bool
    {
        return $this !== self::Ended;
    }

    /** Whether it is charged for (a demo is free). */
    public function isBilled(): bool
    {
        return in_array($this, [self::Active, self::Cancelling], true);
    }
}
