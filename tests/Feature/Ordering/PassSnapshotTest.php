<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Ordering\BranchPulse;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\PassSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../../Support/PassHelpers.php';

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
    passOrder($branch, ['status' => 'picked_up', 'received_at' => now()->subMinutes(20), 'ready_at' => now()->subMinutes(14)], items: [['Manakish', 'general']]);

    $snapshot = app(PassSnapshot::class)->of($branch);

    expect($snapshot['totals']['avg_ticket_seconds'])->toBe(300)
        ->and(passStations($snapshot)['drinks']['avg_seconds'])->toBe(1800)
        ->and(passStations($snapshot)['general']['avg_seconds'])->toBe(360);
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

it('uses the order\'s ready time for station averages when the station did not mark its items', function () {
    $branch = passBranch();
    passOrder($branch, ['status' => 'picked_up', 'received_at' => now()->subMinutes(20), 'ready_at' => now()->subMinutes(15)], items: [['Karak', 'drinks'], ['Manakish', 'general']]);

    $stations = passStations(app(PassSnapshot::class)->of($branch));

    expect($stations['drinks']['avg_seconds'])->toBe(300)
        ->and($stations['general']['avg_seconds'])->toBe(300);
});

it('keeps orders sent back to the kitchen or cancelled off the spike and out of the day\'s totals', function () {
    $branch = passBranch();
    $back = passOrder($branch, ['status' => 'preparing', 'received_at' => now()->subMinutes(8), 'ready_at' => now()->subMinutes(3)]);
    passOrder($branch, ['status' => 'cancelled', 'received_at' => now()->subMinutes(9), 'ready_at' => now()->subMinutes(4)]);
    $ready = passOrder($branch, ['status' => 'ready', 'received_at' => now()->subMinutes(7), 'ready_at' => now()->subMinutes(2)]);

    $snapshot = app(PassSnapshot::class)->of($branch);

    expect(array_column($snapshot['done'], 'order_id'))->toBe([$ready->id])
        ->and($snapshot['totals']['ready_today'])->toBe(1)
        ->and($snapshot['totals']['avg_ticket_seconds'])->toBe(300)
        ->and(collect(passStations($snapshot)['drinks']['tickets'])->pluck('order_id')->all())->toBe([$back->id]);
});
