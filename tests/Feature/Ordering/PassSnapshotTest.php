<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Enums\StationKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Station;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\BranchPulse;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\PassSnapshot;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC')));
afterEach(fn () => CarbonImmutable::setTestNow());

it('hangs an order once per station, with only that station\'s items', function () {
    $branch = passBranch();
    $order = passOrder($branch, items: [['Karak', 'drinks'], ['Zaatar manakish', 'general']]);

    $stations = passStations(app(PassSnapshot::class)->of($branch));
    expect($stations['drinks']['tickets'])->toHaveCount(1)
        ->and($stations['drinks']['tickets'][0]['items'][0]['name'])->toBe('Karak')
        ->and($stations['general']['tickets'][0]['items'][0]['name'])->toBe('Zaatar manakish')
        ->and($stations['general']['tickets'][0]['items'])->toHaveCount(1);

    $order->items()->where('station', 'drinks')->update(['done_at' => now()]);

    $stations = passStations(app(PassSnapshot::class)->of($branch));
    expect($stations['drinks']['tickets'])->toBe([])
        ->and($stations['general']['tickets'])->toHaveCount(1);
});

it('marks tickets late exactly like the branch pulse', function () {
    $branch = passBranch();
    passOrder($branch, ['received_at' => now()->subMinutes(7)]);
    passOrder($branch, ['received_at' => now()->subMinutes(5)]);

    $snapshot = app(PassSnapshot::class)->of($branch);
    $late = collect(passStations($snapshot)['drinks']['tickets'])->pluck('late')->sort()->values()->all();

    expect($late)->toBe([false, true])
        ->and($snapshot['totals']['late'])->toBe(1)
        ->and($snapshot['totals']['late'])->toBe(app(BranchPulse::class)->of($branch)['delayed'])
        ->and(passStations($snapshot)['drinks']['late'])->toBe(1);
});

it('puts items without a station in the kitchen group', function () {
    $branch = passBranch(stations: false);
    passOrder($branch, items: [['Kunafa', null]]);

    $stations = app(PassSnapshot::class)->of($branch)['stations'];
    expect($stations)->toHaveCount(1)
        ->and($stations[0]['kind'])->toBe('general')
        ->and($stations[0]['name'])->toBe(__('ops.pass.kitchen'))
        ->and($stations[0]['tickets'][0]['items'][0]['name'])->toBe('Kunafa');
});

it('lists what to start next: received oldest first, then scheduled due within 15 minutes', function () {
    $branch = passBranch();
    $newer = passOrder($branch, ['received_at' => now()->subMinutes(1)]);
    $older = passOrder($branch, ['received_at' => now()->subMinutes(3)]);
    passOrder($branch, ['status' => 'preparing', 'received_at' => now()->subMinutes(4)]);
    $soon = passOrder($branch, ['status' => 'scheduled', 'received_at' => null, 'prepare_at' => now()->addMinutes(10)]);
    passOrder($branch, ['status' => 'scheduled', 'received_at' => null, 'prepare_at' => now()->addMinutes(30)]);

    $snapshot = app(PassSnapshot::class)->of($branch);

    expect(array_column($snapshot['next'], 'order_id'))->toBe([$older->id, $newer->id, $soon->id])
        ->and($snapshot['totals']['on_pass'])->toBe(3);
    // A scheduled order is not on the rail until it is received.
    expect(collect(passStations($snapshot)['drinks']['tickets'])->pluck('order_id')->all())->not->toContain($soon->id);
});

it('shows orders that became ready in the last 15 minutes on the spike, newest first', function () {
    $branch = passBranch();
    passOrder($branch, ['status' => 'ready', 'received_at' => now()->subMinutes(30), 'ready_at' => now()->subMinutes(20)]);
    $earlier = passOrder($branch, ['status' => 'ready', 'received_at' => now()->subMinutes(14), 'ready_at' => now()->subMinutes(10)]);
    $latest = passOrder($branch, ['status' => 'ready', 'received_at' => now()->subMinutes(6), 'ready_at' => now()->subMinutes(2)]);

    $snapshot = app(PassSnapshot::class)->of($branch);

    expect(array_column($snapshot['done'], 'order_id'))->toBe([$latest->id, $earlier->id])
        ->and($snapshot['totals']['ready_today'])->toBe(3);
});

it('computes the average ticket time and station averages for today', function () {
    $branch = passBranch();
    passOrder($branch, ['status' => 'picked_up', 'received_at' => now()->subMinutes(30), 'ready_at' => now()->subMinutes(26)], items: [['Karak', 'drinks', true]]);
    passOrder($branch, ['status' => 'picked_up', 'received_at' => now()->subMinutes(20), 'ready_at' => now()->subMinutes(14)]);

    $snapshot = app(PassSnapshot::class)->of($branch);

    expect($snapshot['totals']['avg_ticket_seconds'])->toBe(300)
        ->and(passStations($snapshot)['drinks']['avg_seconds'])->toBe(1800);
});

it('never includes another restaurant\'s or another branch\'s orders', function () {
    $branch = passBranch();
    $other = app(CreateBranch::class)->handle($branch->restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    $foreign = passBranch(restaurant(['en' => 'Other', 'ar' => 'آخر'], slug: 'other'));
    passOrder($other);
    passOrder($foreign);
    $mine = passOrder($branch);

    $snapshot = app(PassSnapshot::class)->of($branch);
    $ids = collect($snapshot['stations'])->flatMap(fn (array $s): array => array_column($s['tickets'], 'order_id'))->unique()->values()->all();

    expect($ids)->toBe([$mine->id])
        ->and(array_column($snapshot['next'], 'order_id'))->toBe([$mine->id]);
});

it('runs a fixed number of queries however many orders there are', function () {
    $branch = passBranch();
    $count = function () use ($branch): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PassSnapshot::class)->of($branch->fresh());
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    passOrder($branch, items: [['Karak', 'drinks'], ['Manakish', 'general']]);
    $one = $count();

    foreach (range(1, 29) as $i) {
        passOrder($branch, ['received_at' => now()->subMinutes($i % 9)], items: [['Karak', 'drinks'], ['Manakish', 'general']]);
    }

    expect($count())->toBe($one);
});
