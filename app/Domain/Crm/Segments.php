<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Crm\Enums\Segment;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Groups a restaurant's guests every night from their picked-up orders:
 * New, Regular, VIP, High spender, At risk, Inactive and "Frequent buyer
 * of X" for the restaurant's best sellers. One pass per restaurant with a
 * handful of grouped queries (never one query per guest), and the old rows
 * are replaced in a single transaction so screens never see half a run.
 */
final class Segments
{
    /** Paying guests needed before "top N% by spend" means anything. */
    public const VIP_MIN_GUESTS = 20;

    public function __construct(private readonly GuestStats $stats) {}

    /**
     * @return array<string, int> segment value => guests
     */
    public function compute(Restaurant $restaurant, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $settings = CrmSettings::for($restaurant);
        $this->stats->refreshRestaurant($restaurant->id);

        $guests = Customer::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->where('picked_up_count', '>', 0)
            ->get(['id', 'tier_id', 'first_order_at', 'last_order_at', 'picked_up_count', 'orders_90d', 'spent_90d_minor', 'avg_order_minor']);

        $rows = [];
        $add = function (Segment $segment, int $customerId, int $productId = 0) use (&$rows, $restaurant, $now): void {
            $rows["{$customerId}:{$segment->value}:{$productId}"] = [
                'restaurant_id' => $restaurant->id,
                'customer_id' => $customerId,
                'segment' => $segment->value,
                'product_id' => $productId,
                'computed_at' => $now,
            ];
        };

        if ($guests->isNotEmpty()) {
            $newSince = $now->subDays($settings->int('new_days'));
            $atRiskSince = $now->subDays($settings->int('at_risk_days'));
            $inactiveSince = $now->subDays($settings->int('inactive_days'));
            $topTier = Tier::withoutTenancy()->where('restaurant_id', $restaurant->id)->orderByDesc('threshold_points')->value('id');
            $restaurantAverage = (int) $guests->avg('avg_order_minor');
            $vipCut = $this->topSpendCut(array_values($guests->map(fn (Customer $guest): int => $guest->spent_90d_minor)->all()), $settings->int('vip_top_percent'));

            foreach ($guests as $guest) {
                $last = $guest->last_order_at;

                // Inactive and New can't both hold: an inactive guest's first order is older still.
                if ($last !== null && $last->lt($inactiveSince)) {
                    $add(Segment::Inactive, $guest->id);
                } elseif ($guest->first_order_at !== null && $guest->first_order_at->gte($newSince)) {
                    $add(Segment::New, $guest->id);
                }

                if ($guest->orders_90d >= $settings->int('regular_orders')) {
                    $add(Segment::Regular, $guest->id);
                }

                if (($topTier !== null && $guest->tier_id === $topTier) || ($vipCut !== null && $guest->spent_90d_minor > 0 && $guest->spent_90d_minor >= $vipCut)) {
                    $add(Segment::Vip, $guest->id);
                }

                if ($restaurantAverage > 0 && $guest->avg_order_minor >= $restaurantAverage * $settings->int('high_spender_factor')) {
                    $add(Segment::HighSpender, $guest->id);
                }

                if ($guest->picked_up_count >= 2 && $last !== null && $last->lt($atRiskSince) && $last->gte($inactiveSince)) {
                    $add(Segment::AtRisk, $guest->id);
                }
            }

            foreach ($this->frequentBuyers($restaurant->id, $now, $settings) as [$customerId, $productId]) {
                $add(Segment::FrequentBuyer, $customerId, $productId);
            }
        }

        DB::transaction(function () use ($restaurant, $rows): void {
            CustomerSegment::withoutTenancy()->where('restaurant_id', $restaurant->id)->delete();

            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                CustomerSegment::withoutTenancy()->insert($chunk);
            }
        });

        $counts = array_fill_keys(array_map(fn (Segment $s): string => $s->value, Segment::cases()), 0);

        foreach ($rows as $row) {
            $counts[$row['segment']]++;
        }

        return $counts;
    }

    /**
     * The 90-day spend a guest needs to be in the top N percent (null when nobody spent).
     *
     * @param  list<int>  $spends
     */
    private function topSpendCut(array $spends, int $percent): ?int
    {
        $spends = array_values(array_filter($spends, fn (int $s): bool => $s > 0));

        // A percentage of a handful of guests is meaningless: below the minimum, only the top tier is VIP.
        if (count($spends) < self::VIP_MIN_GUESTS || $percent <= 0) {
            return null;
        }

        rsort($spends);
        $take = max(1, (int) ceil(count($spends) * min(100, $percent) / 100));

        return $spends[$take - 1];
    }

    /**
     * Guests with at least N picked-up orders containing one of the
     * restaurant's best sellers in the last 90 days.
     *
     * @return list<array{0: int, 1: int}> [customer id, product id]
     */
    private function frequentBuyers(int $restaurantId, CarbonImmutable $now, CrmSettings $settings): array
    {
        $since = $now->subDays(GuestStats::WINDOW_DAYS);
        $base = fn () => DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', OrderStatus::PickedUp->value)
            ->where('orders.picked_up_at', '>=', $since)
            ->whereNotNull('order_items.product_id');

        $top = $base()->groupBy('order_items.product_id')
            ->orderByRaw('sum(order_items.quantity) desc')
            ->limit($settings->int('frequent_products'))
            ->pluck('order_items.product_id')
            ->all();

        if ($top === []) {
            return [];
        }

        // "+ 0" makes the bound value a number: SQLite would compare it as text in HAVING.
        return array_values($base()->whereIn('order_items.product_id', $top)
            ->groupBy('orders.customer_id', 'order_items.product_id')
            ->havingRaw('count(distinct orders.id) >= (? + 0)', [$settings->int('frequent_orders')])
            ->get(['orders.customer_id', 'order_items.product_id'])
            ->map(fn (object $row): array => [(int) $row->customer_id, (int) $row->product_id])
            ->all());
    }

    /**
     * Restaurants worth a run tonight: those with any picked-up order.
     *
     * @return list<int>
     */
    public static function restaurantIds(): array
    {
        return array_values(Order::withoutTenancy()->where('status', OrderStatus::PickedUp->value)->distinct()->pluck('restaurant_id')->map(fn ($id): int => (int) $id)->all());
    }
}
