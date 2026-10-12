<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasIcon, HasLabel
{
    case Card = 'card';
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';
    case PayAtPickup = 'pay_at_pickup';

    public function getLabel(): string
    {
        return __("ordering.payment_method.{$this->value}");
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Card => 'heroicon-o-credit-card',
            self::ApplePay, self::GooglePay => 'heroicon-o-device-phone-mobile',
            self::PayAtPickup => 'heroicon-o-banknotes',
        };
    }

    public function isOnline(): bool
    {
        return $this !== self::PayAtPickup;
    }
}
