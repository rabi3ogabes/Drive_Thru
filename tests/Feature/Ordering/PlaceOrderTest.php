<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\UpdateOrderingStatus;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\BranchHour;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\ModifierOption;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Exceptions\OrderRejected;
use App\Domain\Ordering\Models\Order;
use App\Domain\Payments\Actions\CompletePayment;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function shop(array $restaurantAttributes = []): array
{
    $restaurant = restaurant();
    $restaurant->forceFill($restaurantAttributes)->save();
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
    $customer = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455550001', 'name' => 'Sara']);

    return [$restaurant->fresh(), $branch->fresh(), $customer];
}

function item(Restaurant $restaurant, int $price = 2500, array $attributes = []): Product
{
    return Product::factory()->create(array_merge(['restaurant_id' => $restaurant->id, 'price_minor' => $price], $attributes));
}

function orderFor(Customer $customer, Branch $branch, array $lines, array $overrides = []): Order
{
    return app(PlaceOrder::class)->handle(new OrderRequest(...array_merge([
        'customer' => $customer,
        'branch' => $branch,
        'lines' => $lines,
        'paymentMethod' => PaymentMethod::PayAtPickup,
        'idempotencyKey' => (string) Str::uuid(),
    ], $overrides)));
}

it('prices the order on the server, never trusting the browser', function () {
    [$restaurant, $branch, $customer] = shop(['tax_rate_bp' => 500, 'service_fee_bp' => 200]);
    $burger = item($restaurant, 2500, ['discount_percent' => 10]); // 22.50
    $extras = ModifierGroup::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Extras', 'ar' => 'إضافات'], 'max_select' => 2]);
    $cheese = ModifierOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $extras->id, 'name' => ['en' => 'Cheese', 'ar' => 'جبن'], 'price_delta_minor' => 300]);
    $burger->modifierGroups()->attach($extras->id);

    // The browser even sends a made-up price: it's ignored.
    $order = orderFor($customer, $branch, [['key' => 'a', 'product_id' => $burger->id, 'quantity' => 2, 'options' => [$extras->id => [$cheese->id]], 'price_minor' => 1]]);

    // (22.50 + 3.00) × 2 = 51.00; fee 2% = 1.02; VAT 5% on 52.02 = 2.60 → 54.62
    expect($order->subtotal_minor)->toBe(5100)
        ->and($order->service_fee_minor)->toBe(102)
        ->and($order->tax_minor)->toBe(260)
        ->and($order->total_minor)->toBe(5462)
        ->and($order->status)->toBe(OrderStatus::Received)
        ->and($order->items->first()->options[0]['option']['en'])->toBe('Cheese')
        ->and($order->history()->count())->toBe(1);
});

it('places an order exactly once for repeated taps with the same key', function () {
    [$restaurant, $branch, $customer] = shop();
    $fries = item($restaurant, 1000);
    $key = (string) Str::uuid();
    $lines = [['key' => 'a', 'product_id' => $fries->id, 'quantity' => 1]];

    $first = orderFor($customer, $branch, $lines, ['idempotencyKey' => $key]);
    $second = orderFor($customer, $branch, $lines, ['idempotencyKey' => $key]);

    expect($second->id)->toBe($first->id)
        ->and(Order::withoutTenancy()->count())->toBe(1)
        ->and($customer->fresh()->orders_count)->toBe(1);
});

it('refuses items that became unavailable and invalid options', function () {
    [$restaurant, $branch, $customer] = shop();
    $fries = item($restaurant, 1000);
    app(BranchStock::class)->markOutOfStockToday($fries, $branch);

    expect(fn () => orderFor($customer, $branch, [['key' => 'a', 'product_id' => $fries->id, 'quantity' => 1]]))
        ->toThrow(OrderRejected::class);

    $drink = item($restaurant, 800);
    $size = ModifierGroup::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Size', 'ar' => 'الحجم'], 'min_select' => 1, 'max_select' => 1]);
    ModifierOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $size->id, 'name' => ['en' => 'Regular', 'ar' => 'عادي']]);
    $drink->modifierGroups()->attach($size->id);
    $foreign = ModifierOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => ModifierGroup::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'X', 'ar' => 'X']])->id, 'name' => ['en' => 'Sneaky', 'ar' => 'X']]);

    // Required group left empty (no default) → refused; option from another group → refused.
    expect(fn () => orderFor($customer, $branch, [['key' => 'a', 'product_id' => $drink->id, 'quantity' => 1]]))->toThrow(OrderRejected::class)
        ->and(fn () => orderFor($customer, $branch, [['key' => 'a', 'product_id' => $drink->id, 'quantity' => 1, 'options' => [$size->id => [$foreign->id]]]]))->toThrow(OrderRejected::class);
});

it('refuses orders when the branch is closed or paused, and suggests when it opens', function () {
    [$restaurant, $branch, $customer] = shop();
    $fries = item($restaurant);
    BranchHour::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id, 'weekday' => 0, 'opens_at' => '16:00', 'closes_at' => '23:00']);
    $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00', 'Asia/Qatar'));

    try {
        orderFor($customer, $branch->fresh(), [['key' => 'a', 'product_id' => $fries->id, 'quantity' => 1]]);
        $this->fail('Expected the order to be refused.');
    } catch (OrderRejected $e) {
        expect($e->reason)->toBe('closed')->and($e->getMessage())->toContain('16:00');
    }

    // But a scheduled order for 17:00 the same day goes through.
    $order = orderFor($customer, $branch->fresh(), [['key' => 'a', 'product_id' => $fries->id, 'quantity' => 1]], [
        'timing' => OrderTiming::Scheduled,
        'scheduledFor' => CarbonImmutable::parse('2026-10-11 17:00', 'Asia/Qatar'),
    ]);
    expect($order->prepare_at?->setTimezone('Asia/Qatar')->format('H:i'))->toBe('17:00');

    $this->travelTo(CarbonImmutable::parse('2026-10-11 18:00', 'Asia/Qatar'));
    app(UpdateOrderingStatus::class)->pause($branch->fresh(), 30);
    expect(fn () => orderFor($customer, $branch->fresh(), [['key' => 'b', 'product_id' => $fries->id, 'quantity' => 1]]))->toThrow(OrderRejected::class);
});

it('respects kitchen capacity per 15-minute slot and offers the next free slot', function () {
    [$restaurant, $branch, $customer] = shop();
    $branch->forceFill(['capacity_per_slot' => 2])->save();
    $fries = item($restaurant);
    $this->travelTo(CarbonImmutable::parse('2026-10-11 18:02', 'Asia/Qatar'));
    $line = [['key' => 'a', 'product_id' => $fries->id, 'quantity' => 1]];

    orderFor($customer, $branch->fresh(), $line);
    orderFor($customer, $branch->fresh(), $line);

    try {
        orderFor($customer, $branch->fresh(), $line);
        $this->fail('Expected capacity to be full.');
    } catch (OrderRejected $e) {
        expect($e->reason)->toBe('capacity')
            ->and($e->nextSlot?->setTimezone('Asia/Qatar')->format('H:i'))->toBe('18:15');
    }

    // Choosing the suggested slot works.
    $order = orderFor($customer, $branch->fresh(), $line, ['timing' => OrderTiming::Delay, 'delayMinutes' => 15]);
    expect($order->exists)->toBeTrue();
});

it('keeps delays within 5–240 minutes', function (int $minutes, bool $ok) {
    [$restaurant, $branch, $customer] = shop();
    $line = [['key' => 'a', 'product_id' => item($restaurant)->id, 'quantity' => 1]];
    $place = fn () => orderFor($customer, $branch, $line, ['timing' => OrderTiming::Delay, 'delayMinutes' => $minutes]);

    $ok ? expect($place()->delay_minutes)->toBe($minutes) : expect($place)->toThrow(OrderRejected::class);
})->with([[4, false], [5, true], [240, true], [241, false]]);

it('uses up daily stock and refuses more than is left', function () {
    [$restaurant, $branch, $customer] = shop();
    $cake = item($restaurant);
    app(BranchStock::class)->setDailyStock($cake, $branch, 2);

    orderFor($customer, $branch, [['key' => 'a', 'product_id' => $cake->id, 'quantity' => 2]]);

    expect(fn () => orderFor($customer, $branch, [['key' => 'a', 'product_id' => $cake->id, 'quantity' => 1]]))->toThrow(OrderRejected::class);
});

it('prices meals from their slots and freezes the chosen vehicle', function () {
    [$restaurant, $branch, $customer] = shop();
    [$burger, $fries, $rings] = [item($restaurant, 2500), item($restaurant, 1000), item($restaurant, 1300)];
    $meal = item($restaurant, 3300, ['type' => 'combo']);
    $main = ComboSlot::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_product_id' => $meal->id, 'name' => ['en' => 'Main', 'ar' => 'رئيسي']]);
    $side = ComboSlot::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_product_id' => $meal->id, 'name' => ['en' => 'Side', 'ar' => 'جانبي'], 'sort' => 1]);
    ComboSlotOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_slot_id' => $main->id, 'product_id' => $burger->id, 'is_default' => true]);
    ComboSlotOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_slot_id' => $side->id, 'product_id' => $fries->id, 'is_default' => true]);
    $ringsOption = ComboSlotOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_slot_id' => $side->id, 'product_id' => $rings->id, 'price_delta_minor' => 300]);
    $car = CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $customer->id, 'plate' => '123456', 'make' => 'Toyota', 'model' => 'Land Cruiser', 'color' => 'White', 'is_default' => true]);

    $order = orderFor($customer, $branch, [['key' => 'a', 'product_id' => $meal->id, 'quantity' => 1, 'combo' => [$side->id => $ringsOption->id]]]);
    $car->update(['plate' => '999999']);

    expect($order->total_minor)->toBe(3600)
        ->and(collect($order->items->first()->options)->pluck('option.en')->all())->toBe([$burger->getTranslation('name', 'en'), $rings->getTranslation('name', 'en')])
        ->and($order->fresh()->vehicle['plate'])->toBe('123456');
});

it('takes card payments through 3-D Secure, and lets a declined guest try again', function () {
    [$restaurant, $branch, $customer] = shop();
    $order = orderFor($customer, $branch, [['key' => 'a', 'product_id' => item($restaurant)->id, 'quantity' => 1]], ['paymentMethod' => PaymentMethod::Card]);

    expect($order->status)->toBe(OrderStatus::PendingPayment);

    $redirect = app(StartPayment::class)->handle($order, PaymentMethod::Card);
    expect($redirect)->toContain('/pay/');

    app(CompletePayment::class)->handle($order->payment()->first(), approved: false);
    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Failed);

    // Try again with Apple Pay: approved instantly, order goes to the kitchen.
    expect(app(StartPayment::class)->handle($order->fresh(), PaymentMethod::ApplePay))->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Received)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('lets the guest cancel within the window and returns the stock', function () {
    [$restaurant, $branch, $customer] = shop(['cancellation_window_minutes' => 2]);
    $cake = item($restaurant);
    app(BranchStock::class)->setDailyStock($cake, $branch, 1);
    $order = orderFor($customer, $branch, [['key' => 'a', 'product_id' => $cake->id, 'quantity' => 1]]);

    app(CancelOrder::class)->byCustomer($order);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    // The cake is back on sale.
    expect(orderFor($customer, $branch, [['key' => 'a', 'product_id' => $cake->id, 'quantity' => 1]])->exists)->toBeTrue();

    $late = orderFor($customer, $branch, [['key' => 'b', 'product_id' => item($restaurant)->id, 'quantity' => 1]]);
    $this->travel(3)->minutes();
    expect(fn () => app(CancelOrder::class)->byCustomer($late->fresh()))->toThrow(OrderRejected::class);
});

it('never lets a customer order from another restaurant', function () {
    [, $branch] = shop();
    $other = restaurant(['en' => 'Other', 'ar' => 'آخر']);
    $stranger = Customer::withoutTenancy()->create(['restaurant_id' => $other->id, 'phone' => '+97455550002']);

    expect(fn () => orderFor($stranger, $branch, [['key' => 'a', 'product_id' => item($branch->restaurant)->id, 'quantity' => 1]]))
        ->toThrow(OrderRejected::class);
});
