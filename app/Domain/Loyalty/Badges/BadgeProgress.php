<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Badges;

use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Where a guest stands on every badge, worked out from their picked-up
 * orders (cancelled and unpaid ones never count), in the restaurant's time
 * zone. Weeks run Sunday to Saturday.
 */
final class BadgeProgress
{
    /** How far back a streak is looked for. */
    private const STREAK_LOOKBACK_WEEKS = 60;

    /**
     * @return array<string, array{current: int, target: int, earned: ?CarbonImmutable}>
     */
    public static function for(Customer $customer, BadgeSettings $settings, CarbonImmutable $now): array
    {
        $tz = self::timezone($customer);
        $pickups = self::pickups($customer);
        $earned = CustomerBadge::withoutTenancy()->where('customer_id', $customer->id)->get()
            ->mapWithKeys(fn (CustomerBadge $badge): array => [$badge->badge->value => $badge->earned_at]);

        $current = [
            Badge::FirstOrder->value => count($pickups),
            Badge::TenOrders->value => count($pickups),
            Badge::TwentyFiveOrders->value => count($pickups),
            Badge::Explorer->value => OrderItem::withoutTenancy()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.customer_id', $customer->id)
                ->where('orders.status', OrderStatus::PickedUp->value)
                ->whereNotNull('order_items.product_id')
                ->distinct()
                ->count('order_items.product_id'),
            Badge::EarlyBird->value => count(array_filter($pickups, fn (CarbonImmutable $at): bool => $at->setTimezone($tz)->hour < Badge::EARLY_HOUR)),
            Badge::Streak->value => self::streak($customer, $tz, $now)['weeks'],
        ];

        $progress = [];

        foreach (Badge::cases() as $badge) {
            $progress[$badge->value] = [
                'current' => $current[$badge->value],
                'target' => $settings->target($badge),
                'earned' => $earned[$badge->value] ?? null,
            ];
        }

        return $progress;
    }

    /**
     * Weeks in a row with a picked-up order, up to this week (or last week,
     * when nothing was ordered yet this week), and when the next order is
     * due to keep it going.
     *
     * @return array{weeks: int, keepBy: CarbonImmutable}
     */
    public static function streak(Customer $customer, string $tz, CarbonImmutable $now): array
    {
        $thisWeek = $now->setTimezone($tz)->startOfWeek(CarbonInterface::SUNDAY);
        $since = $thisWeek->subWeeks(self::STREAK_LOOKBACK_WEEKS);

        $weeks = [];

        foreach (self::pickups($customer, $since) as $at) {
            $weeks[$at->setTimezone($tz)->startOfWeek(CarbonInterface::SUNDAY)->format('Y-m-d')] = true;
        }

        $orderedThisWeek = isset($weeks[$thisWeek->format('Y-m-d')]);
        $cursor = $orderedThisWeek ? $thisWeek : $thisWeek->subWeek();
        $count = 0;

        while (isset($weeks[$cursor->format('Y-m-d')])) {
            $count++;
            $cursor = $cursor->subWeek();
        }

        // Keep it alive: order before the end of next week (already done this week) or of this week.
        $keepBy = $thisWeek->addWeeks($orderedThisWeek ? 2 : 1)->subSecond();

        return ['weeks' => $count, 'keepBy' => $keepBy->utc()];
    }

    /**
     * @return list<CarbonImmutable>
     */
    private static function pickups(Customer $customer, ?CarbonImmutable $since = null): array
    {
        $query = Order::withoutTenancy()
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::PickedUp->value)
            ->whereNotNull('picked_up_at');

        if ($since !== null) {
            $query->where('picked_up_at', '>=', $since->utc());
        }

        return array_values($query->pluck('picked_up_at')
            ->map(fn (mixed $at): CarbonImmutable => CarbonImmutable::parse($at instanceof \DateTimeInterface ? $at->format('Y-m-d H:i:s') : (string) $at, 'UTC'))
            ->all());
    }

    private static function timezone(Customer $customer): string
    {
        return (string) (Restaurant::query()->whereKey($customer->restaurant_id)->value('timezone') ?? config('app.display_timezone'));
    }
}
