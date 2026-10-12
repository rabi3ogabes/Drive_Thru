<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Alerts;

/** Things the people running a branch must hear about, and how urgent each is. */
enum AlertKind: string
{
    case OrderDelayed = 'order_delayed';
    case GuestWaiting = 'guest_waiting';
    case RefundFailed = 'refund_failed';
    case KitchenOverloaded = 'kitchen_overloaded';
    case WebhookDisabled = 'webhook_disabled';

    /** Critical alerts ignore quiet hours and also go by email (money, or a connected system going dark). */
    public function isCritical(): bool
    {
        return in_array($this, [self::RefundFailed, self::WebhookDisabled], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::OrderDelayed => 'heroicon-o-clock',
            self::GuestWaiting => 'heroicon-o-map-pin',
            self::RefundFailed => 'heroicon-o-exclamation-triangle',
            self::KitchenOverloaded => 'heroicon-o-fire',
            self::WebhookDisabled => 'heroicon-o-link-slash',
        };
    }

    public function color(): string
    {
        return $this->isCritical() ? 'danger' : 'warning';
    }
}
