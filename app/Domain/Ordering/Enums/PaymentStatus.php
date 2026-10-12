<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    /** Pay at pickup: nothing collected yet. */
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return __("ordering.payment_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid, self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
