<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
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
use App\Domain\Ordering\Models\OrderStatusChange;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Ordering\Operations\ScreenPin;
use App\Domain\Payments\Actions\CompletePayment;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Payments\Models\Refund;
use App\Domain\Staff\Actions\SetEmployeePin;
use App\Domain\Staff\Models\Employee;
use App\Http\Middleware\AuthenticateDevice;
use App\Livewire\Ops\LiveBoard;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['payments.fake_enabled' => true]);
    $this->branch = app(CreateBranch::class)->handle(restaurant(slug: 'masar'), ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
    $code = app(CreateDevice::class)->handle($this->branch, DeviceKind::Cashier, 'Cashier');
    $this->token = pairedToken($this, $code->formatted());
    $this->device = Device::withoutTenancy()->latest('id')->first();
});

function paidCardOrder(Branch $branch): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500]);
    $order = app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::Card, idempotencyKey: (string) Str::uuid(),
    ));
    app(StartPayment::class)->handle($order, PaymentMethod::Card);
    app(CompletePayment::class)->handle(Payment::withoutTenancy()->where('order_id', $order->id)->sole(), approved: true);

    return $order->fresh();
}

function staffWithPin(Branch $branch, string $position, string $pin, array $employee = []): User
{
    $user = employeeAt($branch->restaurant, $position, $employee);
    $employment = Employee::withoutTenancy()->where('user_id', $user->id)->sole();
    $employment->branches()->attach($branch->id);
    app(SetEmployeePin::class)->handle($employment, $pin, $user);

    return $user;
}

function cashierBoard($test)
{
    return Livewire::withCookie(AuthenticateDevice::COOKIE, $test->token)
        ->test(LiveBoard::class, ['branchId' => $test->branch->id, 'deviceId' => $test->device->id]);
}

it('refunds an online payment on a paired screen with a manager\'s PIN, recorded under their name', function () {
    $order = paidCardOrder($this->branch);
    $manager = staffWithPin($this->branch, 'branch_manager', '2580');

    cashierBoard($this)
        ->call('select', $order->id)
        ->assertSee(__('ops.actions.refund'))
        ->call('refund', $order->id, 'Customer complaint', '2580')
        ->assertDispatched('ops-toast', tone: 'success');

    $refund = Refund::withoutTenancy()->sole();
    expect($refund)->status->toBe(Refund::SUCCEEDED)->amount_minor->toBe($order->total_minor)->requested_by->toBe('staff:'.$manager->id)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(Activity::query()->where('event', 'screen.pin_used')->sole())->causer_id->toBe($manager->id);
});

it('refuses a refund without a PIN, with a wrong PIN, or from someone not allowed to refund', function () {
    $order = paidCardOrder($this->branch);
    staffWithPin($this->branch, 'cashier', '3691');

    cashierBoard($this)->call('refund', $order->id, 'Customer complaint')->assertDispatched('ops-toast', tone: 'error', message: __('ops.refused.pin_invalid'));
    cashierBoard($this)->call('refund', $order->id, 'Customer complaint', '9999')->assertDispatched('ops-toast', tone: 'error');
    cashierBoard($this)->call('refund', $order->id, 'Customer complaint', '3691')->assertDispatched('ops-toast', tone: 'error', message: __('ops.refused.not_allowed'));

    expect(Refund::withoutTenancy()->count())->toBe(0);
});

it('refuses the PIN of someone who does not work at this branch', function () {
    $order = paidCardOrder($this->branch);
    $other = app(CreateBranch::class)->handle($this->branch->restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    staffWithPin($other, 'branch_manager', '4826');

    cashierBoard($this)->call('refund', $order->id, 'Customer complaint', '4826')->assertDispatched('ops-toast', tone: 'error', message: __('ops.refused.not_allowed'));
    expect(Refund::withoutTenancy()->count())->toBe(0);
});

it('locks PIN entry on a screen after repeated wrong PINs', function () {
    $order = paidCardOrder($this->branch);
    staffWithPin($this->branch, 'branch_manager', '2580');

    foreach (range(1, ScreenPin::MAX_ATTEMPTS) as $i) {
        cashierBoard($this)->call('refund', $order->id, 'Customer complaint', '1357');
    }

    cashierBoard($this)->call('refund', $order->id, 'Customer complaint', '2580')->assertDispatched('ops-toast', tone: 'error', message: __('ops.refused.pin_locked'));
    expect(Refund::withoutTenancy()->count())->toBe(0);

    RateLimiter::clear('screen-pin:'.$this->device->id);
});

it('overrides a status on a paired screen with the PIN of someone allowed to override', function () {
    $this->branch->restaurant->forceFill(['order_override_enabled' => true])->save();
    $order = paidCardOrder($this->branch);
    $manager = staffWithPin($this->branch, 'general_manager', '7410', ['all_branches' => true]);

    cashierBoard($this)->call('setStatus', $order->id, OrderStatus::Ready->value, 'Corrected a mistake', '7410')->assertDispatched('ops-toast', tone: 'success');

    expect($order->fresh()->status)->toBe(OrderStatus::Ready)
        ->and(OrderStatusChange::withoutTenancy()->where('order_id', $order->id)->orderByDesc('id')->first())->actor_type->toBe('staff')->actor_id->toBe($manager->id);
});

it('offers no refund for money taken at the counter', function () {
    $customer = Customer::withoutTenancy()->create(['restaurant_id' => $this->branch->restaurant_id, 'phone' => '+97455550002', 'name' => 'Ali']);
    $product = Product::factory()->create(['restaurant_id' => $this->branch->restaurant_id, 'price_minor' => 2500]);
    $order = app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $this->branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ));
    staffWithPin($this->branch, 'branch_manager', '2580');

    cashierBoard($this)->call('select', $order->id)->assertDontSee(__('ops.actions.refund'))
        ->call('refund', $order->id, 'Customer complaint', '2580')->assertDispatched('ops-toast', tone: 'error', message: __('ops.refused.nothing_to_refund'));
});
