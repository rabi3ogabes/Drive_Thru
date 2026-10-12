<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/** When the kitchen should start the order. */
enum OrderTiming: string implements HasDescription, HasLabel
{
    /** Start right away. */
    case Now = 'now';

    /** Start after a chosen delay (5–240 minutes). */
    case Delay = 'delay';

    /** Start when the guest says they're on the way (or arrives). */
    case OnTheWay = 'on_the_way';

    /** Start for a chosen date and time. */
    case Scheduled = 'scheduled';

    public function getLabel(): string
    {
        return __("ordering.timing.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("ordering.timing.{$this->value}.description");
    }
}
