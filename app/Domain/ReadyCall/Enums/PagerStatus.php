<?php

declare(strict_types=1);

namespace App\Domain\ReadyCall\Enums;

use App\Support\Lang;
use Filament\Support\Contracts\HasLabel;

/** waiting → ready (the phone rings) → collected; anything left over expires. */
enum PagerStatus: string implements HasLabel
{
    case Waiting = 'waiting';
    case Ready = 'ready';
    case Collected = 'collected';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return Lang::text('pager.status.'.$this->value);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Waiting, self::Ready], true);
    }
}
