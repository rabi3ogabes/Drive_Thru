<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The life of an order. Only `TransitionOrder` moves an order between
 * statuses: it enforces this map, writes the history and broadcasts.
 *
 * Arrival and "on the way" are facts recorded next to the status (a guest can
 * arrive while the food is still cooking), and refunds live on the payment.
 */
enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    /** Placed, waiting for an online payment to complete. */
    case PendingPayment = 'pending_payment';

    /** Held: a later start time, or waiting for "I'm on the way". */
    case Scheduled = 'scheduled';

    case Received = 'received';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("ordering.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment, self::Scheduled => 'gray',
            self::Received => 'info',
            self::Preparing => 'warning',
            self::Ready => 'success',
            self::PickedUp => 'gray',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PendingPayment => 'heroicon-o-credit-card',
            self::Scheduled => 'heroicon-o-clock',
            self::Received => 'heroicon-o-inbox-arrow-down',
            self::Preparing => 'heroicon-o-fire',
            self::Ready => 'heroicon-o-check-badge',
            self::PickedUp => 'heroicon-o-hand-thumb-up',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /**
     * Where an order may go next in normal operation.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Received, self::Scheduled, self::Cancelled],
            self::Scheduled => [self::Received, self::Cancelled],
            self::Received => [self::Preparing, self::Ready, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::PickedUp, self::Cancelled],
            self::PickedUp, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /**
     * Override moves (re-enable, reset as new, any status) are for people
     * with the override permission. Nothing goes back to awaiting payment,
     * and a move must change something.
     */
    public function canOverrideTo(self $to): bool
    {
        return $to !== $this && $to !== self::PendingPayment && $this !== self::PendingPayment;
    }

    /** In the kitchen's hands right now. */
    public function isActive(): bool
    {
        return in_array($this, [self::Received, self::Preparing, self::Ready], true);
    }

    /** Not finished yet: still payable, held or in progress. */
    public function isOpen(): bool
    {
        return ! $this->isFinal();
    }

    public function isFinal(): bool
    {
        return $this === self::PickedUp || $this === self::Cancelled;
    }

    /** The customer-facing timeline step (0–3), or null for held, cancelled or awaiting payment. */
    public function step(): ?int
    {
        return match ($this) {
            self::Received => 0,
            self::Preparing => 1,
            self::Ready => 2,
            self::PickedUp => 3,
            default => null,
        };
    }

    /** The order's timestamp column for when it reached this status. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Received => 'received_at',
            self::Preparing => 'preparing_at',
            self::Ready => 'ready_at',
            self::PickedUp => 'picked_up_at',
            self::Cancelled => 'cancelled_at',
            default => null,
        };
    }
}
