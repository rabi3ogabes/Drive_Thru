<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\MarkOnTheWay;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Events\OrderBroadcast;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Http\Controllers\RealtimeAuthController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function liveOrder(?Restaurant $restaurant = null): Order
{
    $restaurant ??= restaurant();
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail '.Str::random(4), 'ar' => 'لوسيل']]);
    $customer = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+9745555'.random_int(1000, 9999), 'name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 2500]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer,
        branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup,
        idempotencyKey: (string) Str::uuid(),
    ));
}

function realtimeAuth(string $channel): array
{
    return ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$channel];
}

it('broadcasts every change to the branch, the restaurant and the guest, without personal data', function () {
    Event::fake([OrderBroadcast::class]);
    $order = liveOrder();

    app(TransitionOrder::class)->handle($order, OrderStatus::Preparing, TransitionOrder::STAFF, 1);
    app(MarkOnTheWay::class)->handle($order->fresh());

    Event::assertDispatched(OrderBroadcast::class, 3);
    Event::assertDispatched(OrderBroadcast::class, function (OrderBroadcast $event) use ($order) {
        $channels = array_map(fn ($c) => $c->name, $event->broadcastOn());
        $payload = json_encode($event->broadcastWith());

        return $event->what === 'status'
            && $event->broadcastWith()['status'] === 'preparing'
            && $event->broadcastWith()['from'] === 'received'
            && $channels === ["private-branch.{$order->branch_id}.orders", "private-restaurant.{$order->restaurant_id}.dashboard", "private-order.{$order->short_code}", 'private-platform.dashboard']
            && ! str_contains($payload, 'Sara') && ! str_contains($payload, '+974');
    });
    Event::assertDispatched(OrderBroadcast::class, fn (OrderBroadcast $e) => $e->what === 'placed');
    Event::assertDispatched(OrderBroadcast::class, fn (OrderBroadcast $e) => $e->what === 'on_the_way');
});

it('lets only staff who work a branch listen to it', function () {
    $order = liveOrder();
    $restaurant = $order->restaurant;
    $access = app(ChannelAccess::class);

    $kitchen = employeeAt($restaurant, 'kitchen');
    Employee::withoutTenancy()->where('user_id', $kitchen->id)->sole()->branches()->sync([$order->branch_id]);
    $otherBranchCashier = employeeAt($restaurant, 'cashier');
    $other = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    Employee::withoutTenancy()->where('user_id', $otherBranchCashier->id)->sole()->branches()->sync([$other->id]);
    $marketing = employeeAt($restaurant, 'marketing', ['all_branches' => true]);
    $stranger = employeeAt(restaurant(['en' => 'Other', 'ar' => 'آخر']), 'general_manager', ['all_branches' => true]);

    expect($access->branchOrders(ownerOf($restaurant), $order->branch_id))->toBeTrue()
        ->and($access->branchOrders($kitchen, $order->branch_id))->toBeTrue()
        ->and($access->branchOrders($otherBranchCashier, $order->branch_id))->toBeFalse()
        ->and($access->branchOrders($marketing, $order->branch_id))->toBeFalse()
        ->and($access->branchOrders($stranger, $order->branch_id))->toBeFalse()
        ->and($access->restaurantDashboard(ownerOf($restaurant), $restaurant->id))->toBeTrue()
        ->and($access->restaurantDashboard($kitchen, $restaurant->id))->toBeFalse()
        ->and($access->restaurantDashboard($stranger, $restaurant->id))->toBeFalse();

    $kitchen->forceFill(['is_active' => false])->save();
    expect($access->branchOrders($kitchen->fresh(), $order->branch_id))->toBeFalse();
});

it('lets a paired screen listen to its own branch only', function () {
    $order = liveOrder();
    $elsewhere = liveOrder();
    $code = app(CreateDevice::class)->handle($order->branch, DeviceKind::Kitchen, 'Grill');
    $token = pairedToken($this, $code->formatted());

    $this->withCookie(AuthenticateDevice::COOKIE, $token)->withCredentials()
        ->postJson(route('realtime.auth'), realtimeAuth("branch.{$order->branch_id}.orders"))->assertOk();
    $this->withCookie(AuthenticateDevice::COOKIE, $token)->withCredentials()
        ->postJson(route('realtime.auth'), realtimeAuth("branch.{$elsewhere->branch_id}.orders"))->assertForbidden();
    $this->withCookie(AuthenticateDevice::COOKIE, $token)->withCredentials()
        ->postJson(route('realtime.auth'), realtimeAuth("restaurant.{$order->restaurant_id}.dashboard"))->assertForbidden();
    $this->defaultCookies = [];
    $this->flushSession();
    $this->postJson(route('realtime.auth'), realtimeAuth("branch.{$order->branch_id}.orders"))->assertForbidden();
});

it('lets a guest listen to their own order only', function () {
    $order = liveOrder();
    $theirs = liveOrder($order->restaurant);
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $order->restaurant_id, 'customer_id' => $order->customer_id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);

    $this->withSession(["customer.{$order->restaurant_id}" => $token])
        ->postJson(route('realtime.auth'), realtimeAuth("order.{$order->short_code}"))->assertOk();
    $this->withSession(["customer.{$order->restaurant_id}" => $token])
        ->postJson(route('realtime.auth'), realtimeAuth("order.{$theirs->short_code}"))->assertForbidden();

    // A signed staff link grants that one order for the browser session.
    $this->withSession([RealtimeAuthController::STAFF_VIEW_SESSION => [$theirs->id]])
        ->postJson(route('realtime.auth'), realtimeAuth("order.{$theirs->short_code}"))->assertOk();

    $this->flushSession();
    app()->forgetScopedInstances(); // as on a fresh request
    $this->postJson(route('realtime.auth'), realtimeAuth("order.{$order->short_code}"))->assertForbidden();
    $this->postJson(route('realtime.auth'), ['socket_id' => 'nope', 'channel_name' => "private-order.{$order->short_code}"])->assertForbidden();
});
