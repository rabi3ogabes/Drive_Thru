<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\MarkOnTheWay;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\ReleaseScheduledOrders;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Jobs\ReleaseScheduledOrder;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Payments\Actions\StartPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array{0: Branch, 1: Customer, 2: Product}
 */
function kitchen(): array
{
    $restaurant = restaurant();
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
    $customer = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455550001', 'name' => 'Sara']);
    $burger = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 2500]);

    return [$branch->fresh(), $customer, $burger];
}

function placeFor(Branch $branch, Customer $customer, Product $product, array $overrides = []): Order
{
    return app(PlaceOrder::class)->handle(new OrderRequest(...array_merge([
        'customer' => $customer,
        'branch' => $branch,
        'lines' => [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        'paymentMethod' => PaymentMethod::PayAtPickup,
        'idempotencyKey' => (string) Str::uuid(),
    ], $overrides)));
}

/** Puts an order straight into a status for testing moves out of it. */
function orderIn(OrderStatus $status): Order
{
    [$branch, $customer, $burger] = kitchen();
    $order = placeFor($branch, $customer, $burger);
    Order::withoutTenancy()->whereKey($order->id)->update(['status' => $status->value]);

    return $order->fresh();
}

beforeEach(fn () => Queue::fake([ReleaseScheduledOrder::class]));

$all = collect(OrderStatus::cases())->flatMap(fn (OrderStatus $from) => collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $to) => ["{$from->value} → {$to->value}" => [$from, $to]]))->all();

it('allows exactly the moves in the state machine, and nothing else', function (OrderStatus $from, OrderStatus $to) {
    $order = orderIn($from);
    $allowed = [
        'pending_payment' => ['received', 'scheduled', 'cancelled'],
        'scheduled' => ['received', 'cancelled'],
        'received' => ['preparing', 'ready', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['picked_up', 'cancelled'],
        'picked_up' => [],
        'cancelled' => [],
    ][$from->value];

    if (! in_array($to->value, $allowed, true)) {
        expect(fn () => app(TransitionOrder::class)->handle($order, $to, TransitionOrder::STAFF, 1))->toThrow(InvalidTransition::class);
        expect($order->fresh()->status)->toBe($from);

        return;
    }

    app(TransitionOrder::class)->handle($order, $to, TransitionOrder::STAFF, 1, 'test');
    $fresh = $order->fresh();
    $history = $fresh->history()->reorder('id', 'desc')->first();

    expect($fresh->status)->toBe($to)
        ->and($history->from)->toBe($from)
        ->and($history->to)->toBe($to)
        ->and($history->actor_type)->toBe(TransitionOrder::STAFF);

    if ($column = $to->timestampColumn()) {
        expect($fresh->{$column})->not->toBeNull();
    }
})->with($all);

it('lets overrides go anywhere except back to awaiting payment, with a reason, and audits them', function (OrderStatus $from, OrderStatus $to) {
    $order = orderIn($from);

    if (! $from->canOverrideTo($to)) {
        expect(fn () => app(TransitionOrder::class)->override($order, $to, TransitionOrder::STAFF, 1, 'Manager decision'))->toThrow(InvalidTransition::class);

        return;
    }

    expect(fn () => app(TransitionOrder::class)->override($order, $to, TransitionOrder::STAFF, 1, '  '))->toThrow(InvalidTransition::class);

    app(TransitionOrder::class)->override($order, $to, TransitionOrder::STAFF, 1, 'Manager decision');

    expect($order->fresh()->status)->toBe($to)
        ->and(DB::table('activity_log')->where('subject_id', $order->id)->where('event', 'like', 'order.%')->exists())->toBeTrue();
})->with($all);

it('re-enables a cancelled order and resets an order as new', function () {
    [$branch, $customer, $burger] = kitchen();
    $manager = ownerOf($branch->restaurant);
    $order = placeFor($branch, $customer, $burger);
    $transition = app(TransitionOrder::class);

    $transition->handle($order, OrderStatus::Preparing, TransitionOrder::STAFF, 1);
    OrderItem::withoutTenancy()->where('order_id', $order->id)->update(['done_at' => now()]);
    $transition->handle($order, OrderStatus::Cancelled, TransitionOrder::STAFF, 1, 'Wrong order');

    expect(fn () => $transition->reEnable(orderIn(OrderStatus::Preparing), TransitionOrder::STAFF, 1, 'x'))->toThrow(InvalidTransition::class);

    $transition->reEnable($order, TransitionOrder::STAFF, $manager->id, 'Customer came back');
    expect($order->fresh())->status->toBe(OrderStatus::Received)->cancelled_at->toBeNull()->cancel_reason->toBeNull();

    $transition->resetAsNew($order, TransitionOrder::STAFF, $manager->id, 'Remake');
    $fresh = $order->fresh();

    expect($fresh->status)->toBe(OrderStatus::Received)
        ->and($fresh->preparing_at)->toBeNull()
        ->and($fresh->items->first()->done_at)->toBeNull()
        ->and($fresh->ready_estimate_at->greaterThan(now()))->toBeTrue();
});

it('refuses a change someone else made first (compare-and-set)', function () {
    $order = orderIn(OrderStatus::Received);

    // Another screen cancels the order right after we read it.
    $raced = false;
    DB::listen(function ($query) use ($order, &$raced) {
        if (! $raced && str_starts_with($query->sql, 'select') && preg_match('/from ["`]orders["`]/', $query->sql) === 1) {
            $raced = true;
            DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']);
        }
    });

    expect(fn () => app(TransitionOrder::class)->handle($order, OrderStatus::Preparing, TransitionOrder::STAFF, 1))
        ->toThrow(InvalidTransition::class, __('ordering.transition.stale'));

    // Nothing of ours was saved (the rival's write shares this test's connection, so the rollback undoes it too).
    expect($order->fresh()->status)->not->toBe(OrderStatus::Preparing)
        ->and($order->history()->where('to', 'preparing')->exists())->toBeFalse();
});

it('has a database guard that rejects unknown statuses', function () {
    $order = orderIn(OrderStatus::Received);

    expect(fn () => DB::table('orders')->where('id', $order->id)->update(['status' => 'teleported']))->toThrow(QueryException::class);
});

it('announces every change after the transaction commits', function () {
    $order = orderIn(OrderStatus::Received);
    Event::fake([OrderStatusChanged::class]);

    DB::transaction(function () use ($order) {
        app(TransitionOrder::class)->handle($order, OrderStatus::Preparing, TransitionOrder::STAFF, 1);
        Event::assertNotDispatched(OrderStatusChanged::class);
    });

    Event::assertDispatched(OrderStatusChanged::class, fn ($e) => $e->from === OrderStatus::Received && $e->to === OrderStatus::Preparing);
});

it('holds later orders as scheduled and releases them at their start time', function () {
    Queue::fake([ReleaseScheduledOrder::class]);
    [$branch, $customer, $burger] = kitchen();

    $now = placeFor($branch, $customer, $burger);
    $later = placeFor($branch, $customer, $burger, ['timing' => OrderTiming::Delay, 'delayMinutes' => 20]);
    $tomorrow = placeFor($branch, $customer, $burger, ['timing' => OrderTiming::Scheduled, 'scheduledFor' => CarbonImmutable::now()->addDay()]);

    expect($now->status)->toBe(OrderStatus::Received)
        ->and($now->received_at)->not->toBeNull()
        ->and($later->status)->toBe(OrderStatus::Scheduled)
        ->and($tomorrow->status)->toBe(OrderStatus::Scheduled);

    Queue::assertPushed(ReleaseScheduledOrder::class, fn ($job) => $job->orderId === $later->id && $job->delay !== null);

    $this->artisan('orders:release')->assertSuccessful();
    expect($later->fresh()->status)->toBe(OrderStatus::Scheduled);

    $this->travel(21)->minutes();
    $this->artisan('orders:release')->assertSuccessful();

    expect($later->fresh()->status)->toBe(OrderStatus::Received)
        ->and($later->fresh()->history()->reorder('id', 'desc')->first()->actor_type)->toBe(TransitionOrder::SYSTEM)
        ->and($tomorrow->fresh()->status)->toBe(OrderStatus::Scheduled);

    // The queued job does the same thing, and repeating it changes nothing.
    (new ReleaseScheduledOrder($later->id))->handle(app(ReleaseScheduledOrders::class));
    expect($later->fresh()->history()->count())->toBe(2);
});

it('holds "on my way" orders until the guest sets off, and records it on any order', function () {
    [$branch, $customer, $burger] = kitchen();
    $held = placeFor($branch, $customer, $burger, ['timing' => OrderTiming::OnTheWay]);
    $now = placeFor($branch, $customer, $burger);

    expect($held->status)->toBe(OrderStatus::Scheduled)->and($held->prepare_at)->toBeNull();

    app(MarkOnTheWay::class)->handle($held);
    app(MarkOnTheWay::class)->handle($held->fresh());
    app(MarkOnTheWay::class)->handle($now);

    expect($held->fresh())->status->toBe(OrderStatus::Received)->on_the_way_at->not->toBeNull()->prepare_at->not->toBeNull()
        ->and($held->fresh()->history()->count())->toBe(2)
        ->and($now->fresh())->status->toBe(OrderStatus::Received)->on_the_way_at->not->toBeNull();
});

it('sends paid scheduled orders to the hold, not the kitchen', function () {
    Queue::fake([ReleaseScheduledOrder::class]);
    [$branch, $customer, $burger] = kitchen();
    $order = placeFor($branch, $customer, $burger, ['paymentMethod' => PaymentMethod::ApplePay, 'timing' => OrderTiming::Delay, 'delayMinutes' => 30]);

    expect($order->status)->toBe(OrderStatus::PendingPayment);
    app(StartPayment::class)->handle($order, PaymentMethod::ApplePay);

    expect($order->fresh()->status)->toBe(OrderStatus::Scheduled);
    Queue::assertPushed(ReleaseScheduledOrder::class);
});
