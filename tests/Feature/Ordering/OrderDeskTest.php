<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Enums\StationKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Branches\Models\Lane;
use App\Domain\Branches\Models\Station;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Ordering\Operations\OperationRefused;
use App\Domain\Ordering\Operations\Operator;
use App\Domain\Ordering\Operations\OrderDesk;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Staff\Models\Employee;
use Illuminate\Support\Str;

function deskBranch(): Branch
{
    return app(CreateBranch::class)->handle(restaurant(), ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
}

function deskOrder(Branch $branch, array $products = [], array $overrides = []): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $products = $products ?: [Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500])];

    return app(PlaceOrder::class)->handle(new OrderRequest(...array_merge([
        'customer' => $customer,
        'branch' => $branch,
        'lines' => array_map(fn (Product $p) => ['key' => (string) $p->id, 'product_id' => $p->id, 'quantity' => 1], $products),
        'paymentMethod' => PaymentMethod::PayAtPickup,
        'idempotencyKey' => (string) Str::uuid(),
    ], $overrides)));
}

function screenAt(Branch $branch, DeviceKind $kind, ?StationKind $station = null): Operator
{
    $stationId = $station === null ? null : Station::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'branch_id' => $branch->id, 'name' => ['en' => $station->value, 'ar' => $station->value], 'kind' => $station])->id;
    app(CreateDevice::class)->handle($branch, $kind, $kind->value.Str::random(3), $stationId);

    return Operator::device(Device::withoutTenancy()->with('station')->latest('id')->first());
}

function desk(): OrderDesk
{
    return app(OrderDesk::class);
}

it('lets the kitchen cook and the window hand over, each only its own part', function () {
    $branch = deskBranch();
    $order = deskOrder($branch);
    $kitchen = screenAt($branch, DeviceKind::Kitchen);
    $window = screenAt($branch, DeviceKind::Pickup);
    $tv = screenAt($branch, DeviceKind::Tv);

    expect(fn () => desk()->advance($tv, $order))->toThrow(OperationRefused::class);

    desk()->advance($kitchen, $order);
    desk()->advance($kitchen, $order);
    expect($order->fresh()->status)->toBe(OrderStatus::Ready);

    expect(fn () => desk()->advance($kitchen, $order->fresh()))->toThrow(OperationRefused::class)
        ->and(fn () => desk()->collectPayment($kitchen, $order->fresh(), 'cash'))->toThrow(OperationRefused::class)
        ->and(fn () => desk()->complete($window, $order->fresh()))->toThrow(OperationRefused::class, __('ops.refused.unpaid'));

    desk()->collectPayment($window, $order->fresh(), 'cash');
    desk()->collectPayment($window, $order->fresh(), 'cash');
    desk()->complete($window, $order->fresh());

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(OrderStatus::PickedUp)
        ->and($fresh->payment_status)->toBe(PaymentStatus::Paid)
        ->and(Payment::withoutTenancy()->where('order_id', $order->id)->count())->toBe(1)
        ->and($fresh->picked_up_at)->not->toBeNull()
        ->and($fresh->history()->reorder('id', 'desc')->first()->actor_type)->toBe(TransitionOrder::DEVICE);
});

it('keeps every screen and person inside their own branch', function () {
    $branch = deskBranch();
    $other = app(CreateBranch::class)->handle($branch->restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    $order = deskOrder($branch);

    expect(fn () => desk()->advance(screenAt($other, DeviceKind::Kitchen), $order))->toThrow(OperationRefused::class);

    $cashier = employeeAt($branch->restaurant, 'cashier');
    Employee::withoutTenancy()->where('user_id', $cashier->id)->sole()->branches()->sync([$other->id]);
    expect(fn () => desk()->advance(Operator::staff($cashier, $branch->restaurant_id), $order))->toThrow(OperationRefused::class);

    $stranger = employeeAt(restaurant(['en' => 'Other', 'ar' => 'آخر']), 'general_manager', ['all_branches' => true]);
    expect(fn () => desk()->advance(Operator::staff($stranger, $branch->restaurant_id), $order))->toThrow(OperationRefused::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Received);
});

it('routes items to stations and readies the order when every station is done', function () {
    $branch = deskBranch();
    $burger = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'station' => StationKind::Grill]);
    $cola = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'station' => StationKind::Drinks]);
    $order = deskOrder($branch, [$burger, $cola]);
    $grill = screenAt($branch, DeviceKind::Kitchen, StationKind::Grill);
    $drinks = screenAt($branch, DeviceKind::Kitchen, StationKind::Drinks);

    expect($grill->station)->toBe(StationKind::Grill)
        ->and($order->items->pluck('station')->all())->toBe([StationKind::Grill, StationKind::Drinks]);

    desk()->stationDone($grill, $order, StationKind::Grill);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    desk()->stationDone($drinks, $order->fresh(), StationKind::Drinks);
    expect($order->fresh()->status)->toBe(OrderStatus::Ready)
        ->and($order->fresh()->items->every(fn ($i) => $i->done_at !== null))->toBeTrue();
});

it('cancels with a reason and re-enables, but never after a refund', function () {
    $branch = deskBranch();
    $cashier = screenAt($branch, DeviceKind::Cashier);
    $order = deskOrder($branch);

    expect(fn () => desk()->cancel($cashier, $order, ' '))->toThrow(OperationRefused::class, __('ops.refused.reason'));

    desk()->cancel($cashier, $order, 'Customer left');
    expect($order->fresh())->status->toBe(OrderStatus::Cancelled)->cancel_reason->toBe('Customer left');

    desk()->reEnable($cashier, $order->fresh(), 'Customer came back');
    expect($order->fresh()->status)->toBe(OrderStatus::Received);

    $paid = deskOrder($branch, overrides: ['paymentMethod' => PaymentMethod::ApplePay]);
    app(StartPayment::class)->handle($paid, PaymentMethod::ApplePay);
    desk()->cancel($cashier, $paid->fresh(), 'Out of stock');

    expect($paid->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(fn () => desk()->reEnable($cashier, $paid->fresh(), 'Oops'))->toThrow(OperationRefused::class, __('ops.refused.refunded'));
});

it('allows overrides only with the permission and the restaurant switch on', function () {
    $branch = deskBranch();
    $order = deskOrder($branch);
    $owner = ownerOf($branch->restaurant);
    $cashier = employeeAt($branch->restaurant, 'cashier', ['all_branches' => true]);

    expect(fn () => desk()->resetAsNew(Operator::staff($owner, $branch->restaurant_id), $order, 'Remake'))->toThrow(OperationRefused::class, __('ops.refused.override'));

    $branch->restaurant->forceFill(['order_override_enabled' => true])->save();

    expect(fn () => desk()->setStatus(Operator::staff($cashier, $branch->restaurant_id), $order, OrderStatus::Ready, 'Done'))->toThrow(OperationRefused::class)
        ->and(fn () => desk()->setStatus(screenAt($branch, DeviceKind::Cashier), $order, OrderStatus::Ready, 'Done'))->toThrow(OperationRefused::class);

    desk()->setStatus(Operator::staff($owner, $branch->restaurant_id), $order, OrderStatus::Ready, 'Bagged early');
    desk()->resetAsNew(Operator::staff($owner, $branch->restaurant_id), $order->fresh(), 'Remake');

    expect($order->fresh()->status)->toBe(OrderStatus::Received)
        ->and($order->fresh()->ready_at)->toBeNull();
});

it('finds orders at the window by QR, pickup code or short code, in its own branch only', function () {
    $branch = deskBranch();
    $order = deskOrder($branch);
    $window = screenAt($branch, DeviceKind::Pickup);
    $elsewhere = screenAt(app(CreateBranch::class)->handle($branch->restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]), DeviceKind::Pickup);

    $byQr = desk()->lookup($window, $branch->id, "{$order->short_code}-{$order->pickup_code}");
    $byPickup = desk()->lookup($window, $branch->id, ' '.$order->pickup_code.' ');
    $byShort = desk()->lookup($window, $branch->id, strtolower($order->short_code));
    $wrongQr = desk()->lookup($window, $branch->id, "{$order->short_code}-000000");

    expect($byQr)->order->id->toBe($order->id)->verified->toBeTrue()
        ->and($byPickup)->order->id->toBe($order->id)->verified->toBeTrue()
        ->and($byShort)->order->id->toBe($order->id)->verified->toBeFalse()
        ->and($wrongQr['order'])->toBeNull()
        ->and(fn () => desk()->lookup($elsewhere, $branch->id, $order->pickup_code))->toThrow(OperationRefused::class);
});

it('records arrival once, with the lane, and releases a held order', function () {
    $branch = deskBranch();
    $lane = Lane::withoutTenancy()->where('branch_id', $branch->id)->first() ?? Lane::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'branch_id' => $branch->id, 'name' => ['en' => 'Lane 2', 'ar' => 'المسار 2']]);
    $order = deskOrder($branch, overrides: ['timing' => OrderTiming::OnTheWay]);

    expect($order->status)->toBe(OrderStatus::Scheduled);

    desk()->markArrived(screenAt($branch, DeviceKind::Cashier), $order, $lane->id);
    $first = $order->fresh()->arrived_at;
    desk()->markArrived(screenAt($branch, DeviceKind::Pickup), $order->fresh());

    expect($order->fresh())->status->toBe(OrderStatus::Received)->lane_id->toBe($lane->id)->arrival_source->toBe('staff')
        ->and($order->fresh()->arrived_at->equalTo($first))->toBeTrue()
        ->and(fn () => desk()->markArrived(screenAt($branch, DeviceKind::Kitchen), $order->fresh()))->toThrow(OperationRefused::class);
});

it('refuses a step the order has moved past', function () {
    $branch = deskBranch();
    $order = deskOrder($branch);
    $kitchen = screenAt($branch, DeviceKind::Kitchen);

    desk()->cancel(screenAt($branch, DeviceKind::Cashier), $order, 'Duplicate');

    expect(fn () => desk()->stationDone($kitchen, $order->fresh(), StationKind::General))->toThrow(InvalidTransition::class);
});
