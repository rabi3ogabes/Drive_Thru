<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Badges;

use App\Support\Lang;

/** The milestones a guest can reach. Targets are fixed, except the streak's (set by the owner). */
enum Badge: string
{
    case FirstOrder = 'first_order';
    case TenOrders = 'orders_10';
    case TwentyFiveOrders = 'orders_25';
    case Explorer = 'explorer';
    case EarlyBird = 'early_bird';
    case Streak = 'streak';

    /** Picked-up orders before this hour (restaurant time) count for the early bird. */
    public const EARLY_HOUR = 9;

    public function target(int $streakWeeks): int
    {
        return match ($this) {
            self::FirstOrder => 1,
            self::TenOrders => 10,
            self::TwentyFiveOrders => 25,
            self::Explorer, self::EarlyBird => 5,
            self::Streak => $streakWeeks,
        };
    }

    public function label(): string
    {
        return Lang::text('loyalty.badges.names.'.$this->value);
    }

    public function description(int $streakWeeks): string
    {
        return Lang::text('loyalty.badges.descriptions.'.$this->value, ['count' => $this->target($streakWeeks)]);
    }

    /** A simple line drawing, 24 × 24, stroke only. */
    public function icon(): string
    {
        return match ($this) {
            self::FirstOrder => 'M12 3l2.5 5.2 5.7.8-4.1 4 1 5.6L12 16l-5.1 2.6 1-5.6-4.1-4 5.7-.8L12 3Z',
            self::TenOrders => 'M6 4h12v6a6 6 0 0 1-12 0V4Zm0 2H3v2a3 3 0 0 0 3 3m12-5h3v2a3 3 0 0 1-3 3M9 21h6m-3-5v5',
            self::TwentyFiveOrders => 'M12 2l3 6 6 .9-4.5 4.3 1.1 6.3L12 16.5 6.4 19.5l1.1-6.3L3 8.9 9 8l3-6Zm0 6v5',
            self::Explorer => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm3.5-12.5-2 5-5 2 2-5 5-2Z',
            self::EarlyBird => 'M12 3v2m0 14v2M5 12H3m18 0h-2M6.3 6.3 4.9 4.9m14.2 14.2-1.4-1.4M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
            self::Streak => 'M12 22c4 0 7-2.7 7-7 0-4.5-4-7-5-11-2 2-3 4-3 6-1-1-1.5-2-1.5-3C7 9 5 11.5 5 15c0 4.3 3 7 7 7Z',
        };
    }
}
