<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * What the restaurant knows about a guest's visits, counted from picked-up
 * orders only: a cancelled or unpaid order is not a visit. Refreshed after
 * every pickup or cancellation, and for everyone at night (the 90-day
 * window moves even when nobody orders).
 */
final class GuestStats
{
    public const WINDOW_DAYS = 90;

    public function refresh(Customer $customer): void
    {
        $this->refreshRestaurant($customer->restaurant_id, [$customer->id]);
    }

    /**
     * One grouped query per restaurant, then an update per guest whose
     * numbers changed. Guests without a picked-up order go back to zero.
     *
     * @param  list<int>|null  $customerIds  null for every guest of the restaurant
     */
    public function refreshRestaurant(int $restaurantId, ?array $customerIds = null): int
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $rows = Order::withoutTenancy()
            ->where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::PickedUp->value)
            ->whereNotNull('customer_id')
            ->when($customerIds !== null, fn ($query) => $query->whereIn('customer_id', $customerIds))
            ->groupBy('customer_id')
            ->select('customer_id')
            ->selectRaw('min(picked_up_at) as first_at, max(picked_up_at) as last_at, count(*) as orders, sum(total_minor) as spent')
            ->selectRaw('sum(case when picked_up_at >= ? then 1 else 0 end) as orders_90d', [$since])
            ->selectRaw('sum(case when picked_up_at >= ? then total_minor else 0 end) as spent_90d', [$since])
            ->toBase()
            ->get()
            ->keyBy('customer_id');

        $changed = 0;

        Customer::withoutTenancy()
            ->where('restaurant_id', $restaurantId)
            ->when($customerIds !== null, fn ($query) => $query->whereKey($customerIds))
            ->chunkById(500, function ($customers) use ($rows, &$changed): void {
                DB::transaction(function () use ($customers, $rows, &$changed): void {
                    foreach ($customers as $customer) {
                        $row = $rows->get($customer->id);
                        $orders = (int) ($row->orders ?? 0);
                        $spent = (int) ($row->spent ?? 0);

                        $customer->forceFill([
                            'first_order_at' => $row->first_at ?? null,
                            'last_order_at' => $row->last_at ?? null,
                            'picked_up_count' => $orders,
                            'orders_90d' => (int) ($row->orders_90d ?? 0),
                            'spent_90d_minor' => (int) ($row->spent_90d ?? 0),
                            'avg_order_minor' => $orders > 0 ? intdiv($spent, $orders) : 0,
                        ]);

                        if ($customer->isDirty()) {
                            $customer->saveQuietly();
                            $changed++;
                        }
                    }
                });
            });

        return $changed;
    }
}
