<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Actions\RevokeDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Staff\Models\Employee;
use App\Http\Middleware\AuthenticateDevice;
use App\Livewire\Ops\CommandCenter;
use App\Livewire\Ops\ExecutiveBoard;
use App\Livewire\Ops\KitchenDisplay;
use App\Livewire\Ops\LiveBoard;
use App\Livewire\Ops\PickupWindow;
use Illuminate\Support\Str;
use Livewire\Livewire;

function opsBranch(): Branch
{
    return app(CreateBranch::class)->handle(restaurant(slug: 'masar'), ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
}

function opsOrder(Branch $branch): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara Ali']);
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500, 'name' => ['en' => 'Classic Burger', 'ar' => 'برجر كلاسيك']]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer,
        branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 2, 'notes' => 'No onions']],
        paymentMethod: PaymentMethod::PayAtPickup,
        idempotencyKey: (string) Str::uuid(),
    ));
}

/** Pairs a device and returns [device, token]. */
function paired($test, Branch $branch, DeviceKind $kind): array
{
    $code = app(CreateDevice::class)->handle($branch, $kind, $kind->value);
    $token = pairedToken($test, $code->formatted());

    return [Device::withoutTenancy()->latest('id')->first(), $token];
}

it('shows each paired screen its own live view', function (DeviceKind $kind, string $see) {
    $branch = opsBranch();
    opsOrder($branch);
    [, $token] = paired($this, $branch, $kind);

    $this->withCookie(AuthenticateDevice::COOKIE, $token)->get(route('screen.show'))
        ->assertOk()
        ->assertSee(__($see))
        ->assertSee('data-live-channel="branch.'.$branch->id.'.orders"', false)
        ->assertSee(route('realtime.auth'), false);
})->with([
    'kitchen' => [DeviceKind::Kitchen, 'ops.kitchen.columns.received'],
    'cashier' => [DeviceKind::Cashier, 'ops.board.kpi.orders_today'],
    'pickup' => [DeviceKind::Pickup, 'ops.pickup.title'],
    'tv' => [DeviceKind::Tv, 'ops.tv.sales_today'],
]);

it('runs the kitchen from a paired tablet, and stops the moment it is revoked', function () {
    $branch = opsBranch();
    $order = opsOrder($branch);
    [$device, $token] = paired($this, $branch, DeviceKind::Kitchen);

    $kds = Livewire::withCookie(AuthenticateDevice::COOKIE, $token)
        ->test(KitchenDisplay::class, ['branchId' => $branch->id, 'deviceId' => $device->id])
        ->assertSee($order->displayNumber())
        ->assertSee('Classic Burger')
        ->assertSee('No onions')
        ->call('advance', $order->id)
        ->assertDispatched('ops-toast');

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $kds->call('cancel', $order->id, 'Out of stock');
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    $kds->call('reEnable', $order->id, 'Cancelled by mistake');
    expect($order->fresh()->status)->toBe(OrderStatus::Received);

    app(RevokeDevice::class)->handle($device);
    $kds->call('advance', $order->id)->assertStatus(401);
    expect($order->fresh()->status)->toBe(OrderStatus::Received);
});

it('chimes only for orders the screen has not seen yet', function () {
    $branch = opsBranch();
    opsOrder($branch);
    [$device, $token] = paired($this, $branch, DeviceKind::Kitchen);

    $kds = Livewire::withCookie(AuthenticateDevice::COOKIE, $token)->test(KitchenDisplay::class, ['branchId' => $branch->id, 'deviceId' => $device->id])
        ->assertNotDispatched('ops-new-orders');

    opsOrder($branch);
    $kds->call('$refresh')->assertDispatched('ops-new-orders', count: 1);
});

it('gives the cashier the board, the order panel and payment collection', function () {
    $branch = opsBranch();
    $order = opsOrder($branch);
    [$device, $token] = paired($this, $branch, DeviceKind::Cashier);

    $board = Livewire::withCookie(AuthenticateDevice::COOKIE, $token)
        ->test(LiveBoard::class, ['branchId' => $branch->id, 'deviceId' => $device->id])
        ->assertSee($order->displayNumber())
        ->assertSee('Sara')
        ->assertDontSee('+97455550001')
        ->set('filter', 'ready')
        ->assertDontSee('wire:click="select('.$order->id.')"', false)
        ->set('filter', 'nonsense')
        ->assertSet('filter', 'all')
        ->assertSee('wire:click="select('.$order->id.')"', false)
        ->call('select', $order->id)
        ->assertSee(__('ops.actions.collect_cash'))
        ->assertSee(route('short.order.staff', ['code' => $order->short_code]), false);

    $board->call('collect', $order->id, 'cash');
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);

    $board->call('advance', $order->id)->call('advance', $order->id)->call('advance', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::PickedUp);

    $board->set('filter', 'completed')->assertSee($order->displayNumber());
});

it('verifies, collects and hands over at the pickup window', function () {
    $branch = opsBranch();
    $order = opsOrder($branch);
    Order::withoutTenancy()->whereKey($order->id)->update(['status' => 'ready', 'ready_at' => now(), 'arrived_at' => now()]);
    [$device, $token] = paired($this, $branch, DeviceKind::Pickup);

    $window = Livewire::withCookie(AuthenticateDevice::COOKIE, $token)
        ->test(PickupWindow::class, ['branchId' => $branch->id, 'deviceId' => $device->id])
        ->assertSee(__('ops.pickup.arrived_title'))
        ->call('open', $order->id)
        ->assertSet('verified', false)
        ->call('complete')
        ->assertSet('notice', __('ops.pickup.verify_first'))
        ->set('confirmCode', '000000')
        ->call('verify')
        ->assertSet('verified', false)
        ->set('code', "{$order->short_code}-{$order->pickup_code}")
        ->call('lookup')
        ->assertSet('orderId', $order->id)
        ->assertSet('verified', true)
        ->call('collect', 'card')
        ->call('complete');

    expect($order->fresh())->status->toBe(OrderStatus::PickedUp)->payment_status->toBe(PaymentStatus::Paid);
    $window->assertSet('orderId', null);
});

it('opens staff screens only for the people and branches they cover', function () {
    $branch = opsBranch();
    $restaurant = $branch->restaurant;
    $kitchen = employeeAt($restaurant, 'kitchen');
    Employee::withoutTenancy()->where('user_id', $kitchen->id)->sole()->branches()->sync([$branch->id]);
    $marketing = employeeAt($restaurant, 'marketing', ['all_branches' => true]);
    $other = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);

    $this->get(route('ops.screen', [$restaurant, $branch->code, 'kitchen']))->assertRedirect(route('filament.app.auth.login'));

    $this->actingAs($kitchen)->get(route('ops.screen', [$restaurant, $branch->code, 'kitchen']))->assertOk()->assertSee(__('ops.screens.kitchen'));
    $this->actingAs($kitchen)->get(route('ops.screen', [$restaurant, $branch->code, 'pickup']))->assertForbidden();
    $this->actingAs($kitchen)->get(route('ops.screen', [$restaurant, $other->code, 'kitchen']))->assertForbidden();
    $this->actingAs($kitchen)->get(route('ops.overview', $restaurant))->assertForbidden();
    $this->actingAs($kitchen)->get(route('ops.home', $restaurant))->assertRedirect(route('ops.screen', [$restaurant, $branch->code, 'kitchen']));
    $this->actingAs($marketing)->get(route('ops.screen', [$restaurant, $branch->code, 'live']))->assertForbidden();

    $owner = ownerOf($restaurant);
    $this->actingAs($owner)->get(route('ops.home', $restaurant))->assertRedirect(route('ops.overview', $restaurant));
    $this->actingAs($owner)->get(route('ops.overview', $restaurant))->assertOk()->assertSee('Lusail')->assertSee('Pearl');
    foreach (['kitchen', 'live', 'pickup', 'tv'] as $screen) {
        $this->actingAs($owner)->get(route('ops.screen', [$restaurant, $branch->code, $screen]))->assertOk();
    }

    $stranger = employeeAt(restaurant(['en' => 'Other', 'ar' => 'آخر'], slug: 'other'), 'general_manager', ['all_branches' => true]);
    $this->actingAs($stranger)->get(route('ops.screen', [$restaurant, $branch->code, 'live']))->assertForbidden();
    $this->actingAs($stranger)->get(route('ops.overview', $restaurant))->assertForbidden();
});

it('shows the TV and the executive board with live numbers', function () {
    $branch = opsBranch();
    $order = opsOrder($branch);
    Order::withoutTenancy()->whereKey($order->id)->update(['received_at' => now()->subMinutes(30)]);
    $owner = ownerOf($branch->restaurant);

    Livewire::actingAs($owner)->test(CommandCenter::class, ['branchId' => $branch->id])
        ->assertSee(__('ops.tv.in_kitchen'))
        ->assertSee(__('ops.alerts.delayed', ['number' => $order->displayNumber(), 'minutes' => 30]));

    Livewire::actingAs($owner)->test(ExecutiveBoard::class, ['restaurantId' => $branch->restaurant_id])
        ->assertSee($branch->name)
        ->assertSee(__('ops.health.busy'));

    Livewire::actingAs(employeeAt($branch->restaurant, 'kitchen'))->test(ExecutiveBoard::class, ['restaurantId' => $branch->restaurant_id])->assertForbidden();
});

it('renders every screen in Arabic', function () {
    $branch = opsBranch();
    opsOrder($branch);
    $owner = ownerOf($branch->restaurant);

    foreach (['kitchen', 'live', 'pickup', 'tv'] as $screen) {
        $this->actingAs($owner)->withSession(['locale' => 'ar'])->get(route('ops.screen', [$branch->restaurant, $branch->code, $screen]))
            ->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('ops.screens.'.$screen, [], 'ar'));
    }
});
