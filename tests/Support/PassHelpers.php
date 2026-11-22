<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Enums\StationKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Station;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Support\Str;

/* Shared by the Pass board tests: a branch with a drinks bar and a kitchen, and orders with exact timestamps. */

function passBranch(?Restaurant $restaurant = null, bool $stations = true): Branch
{
    $restaurant ??= restaurant(slug: 'tea-time');
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 6, 'capacity_per_slot' => 4]);

    if ($stations) {
        Station::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id, 'name' => ['en' => 'Drinks bar', 'ar' => 'ركن المشروبات'], 'kind' => StationKind::Drinks, 'sort' => 0]);
        Station::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id, 'name' => ['en' => 'Kitchen', 'ar' => 'المطبخ'], 'kind' => StationKind::General, 'sort' => 1]);
    }

    return $branch;
}

/**
 * @param  list<array{0: string, 1: ?string, 2?: bool}>  $items  [name, station, done]
 */
function passOrder(Branch $branch, array $attributes = [], array $items = [['Karak', 'drinks']]): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);

    $order = Order::withoutTenancy()->forceCreate(array_merge([
        'restaurant_id' => $branch->restaurant_id,
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'number' => 100 + Order::withoutTenancy()->where('restaurant_id', $branch->restaurant_id)->count() + 1,
        'short_code' => Str::upper(Str::random(8)),
        'pickup_code' => '123456',
        'status' => 'received',
        'payment_status' => 'paid',
        'payment_method' => 'pay_at_pickup',
        'currency' => 'QAR',
        'subtotal_minor' => 1000,
        'total_minor' => 1000,
        'prep_minutes' => 6,
        'idempotency_key' => (string) Str::uuid(),
        'placed_at' => now(),
        'received_at' => now(),
    ], $attributes));

    foreach ($items as $item) {
        $order->items()->create([
            'restaurant_id' => $branch->restaurant_id,
            'name' => ['en' => $item[0], 'ar' => $item[0]],
            'quantity' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'station' => $item[1],
            'done_at' => ($item[2] ?? false) ? now() : null,
        ]);
    }

    return $order;
}

/** @return array<string, array<string, mixed>> */
function passStations(array $snapshot): array
{
    return collect($snapshot['stations'])->keyBy('kind')->all();
}
