<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\UpdateOrderingStatus;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\ModifierOption;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Cart;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Account;
use App\Livewire\Customer\BranchMenu;
use App\Livewire\Customer\Checkout;
use App\Livewire\Customer\Orders;
use App\Livewire\Customer\OrderStatus as OrderPage;
use App\Support\Money;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * @return array{0: Restaurant, 1: Branch, 2: Product}
 */
function guestShop(string $slug = 'masar'): array
{
    $restaurant = restaurant(['en' => ucfirst($slug), 'ar' => $slug], slug: $slug);
    $restaurant->forceFill(['tax_rate_bp' => 500])->save();
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
    $burgers = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Burgers', 'ar' => 'برجر']]);
    $burger = Product::factory()->create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $burgers->id, 'price_minor' => 2500, 'name' => ['en' => 'Classic Burger', 'ar' => 'برجر كلاسيك']]);

    return [$restaurant->fresh(), $branch->fresh(), $burger];
}

/** Signs a guest in for the rest of the test, the way CustomerAuth does. */
function signInGuest(Restaurant $restaurant, Customer $customer): void
{
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $customer->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$restaurant->id}", $token);
    app()->forgetScopedInstances(); // as on a fresh request
}

function regular(Restaurant $restaurant, string $phone = '+97455550001'): Customer
{
    $customer = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => $phone, 'name' => 'Sara']);
    CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $customer->id, 'plate' => '123456', 'color' => 'White', 'is_default' => true]);

    return $customer;
}

function bagWith(Restaurant $restaurant, Product $product, int $quantity = 1, array $options = []): void
{
    app(Cart::class)->add($restaurant->id, $product->id, $quantity, $options);
}

it('takes a new guest from menu to placed order with a code, a car and server totals', function () {
    [$restaurant, $branch, $burger] = guestShop();
    $size = ModifierGroup::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Size', 'ar' => 'الحجم'], 'min_select' => 1, 'max_select' => 1]);
    $large = ModifierOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $size->id, 'name' => ['en' => 'Large', 'ar' => 'كبير'], 'price_delta_minor' => 500]);
    $burger->modifierGroups()->attach($size->id);

    Livewire::test(BranchMenu::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->call('openProduct', $burger->id)
        ->assertSet('sheet.id', $burger->id)
        ->call('add', $burger->id, 2, [$size->id => [$large->id]], [], 'No onions')
        ->assertSet('sheet', null)
        ->assertDispatched('toast')
        ->assertSee(__('guest.menu.view_bag'));

    // (25 + 5) × 2 = 60.00, VAT 5% = 3.00 → 63.00, exactly what the bag shows.
    $checkout = Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->assertSee(Money::format(6300, $restaurant->currency))
        ->assertSee(__('guest.checkout.who'))
        ->set('phone', '5555 1234')
        ->call('sendCode')
        ->assertSet('codeSent', true);

    $checkout->set('code', $checkout->get('demoCode'))
        ->call('verifyCode')
        ->assertSet('addingVehicle', true)
        ->set('name', 'Huda')
        ->set('plate', 'ab 1234')
        ->set('payment', 'pay_at_pickup')
        ->call('place')
        ->assertHasNoErrors();

    $order = Order::withoutTenancy()->sole();
    $checkout->assertRedirect(route('customer.order', [$restaurant, $order]).'?placed=1');

    expect($order->total_minor)->toBe(6300)
        ->and($order->status)->toBe(OrderStatus::Received)
        ->and($order->vehicle['plate'])->toBe('AB 1234')
        ->and($order->items->sole()->notes)->toBe('No onions')
        ->and($order->customer->name)->toBe('Huda')
        ->and(app(Cart::class)->lines($restaurant->id))->toBe([]);
});

it('lets a returning guest order in one tap with everything remembered', function () {
    [$restaurant, $branch, $burger] = guestShop();
    $customer = regular($restaurant);
    signInGuest($restaurant, $customer);
    bagWith($restaurant, $burger);

    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->assertSet('name', 'Sara')
        ->assertSet('vehicleId', $customer->vehicles()->value('id'))
        ->assertSet('addingVehicle', false)
        ->set('payment', 'pay_at_pickup')
        ->call('place')
        ->assertHasNoErrors();

    expect(Order::withoutTenancy()->sole()->vehicle['plate'])->toBe('123456');
});

it('never creates two orders from a double tap', function () {
    [$restaurant, $branch, $burger] = guestShop();
    signInGuest($restaurant, regular($restaurant));
    bagWith($restaurant, $burger);

    $page = Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])->set('payment', 'pay_at_pickup');
    $page->call('place');
    $page->call('place');

    expect(Order::withoutTenancy()->count())->toBe(1);
});

it('shows and charges the current price when the menu changed after adding', function () {
    [$restaurant, $branch, $burger] = guestShop();
    signInGuest($restaurant, regular($restaurant));
    bagWith($restaurant, $burger);

    $burger->forceFill(['price_minor' => 3000])->save();

    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->assertSee(Money::format(3150, $restaurant->currency))
        ->assertDontSee(Money::format(2625, $restaurant->currency))
        ->set('payment', 'pay_at_pickup')
        ->call('place');

    expect(Order::withoutTenancy()->sole()->total_minor)->toBe(3150);
});

it('blocks the order and explains when an item sold out', function () {
    [$restaurant, $branch, $burger] = guestShop();
    signInGuest($restaurant, regular($restaurant));
    bagWith($restaurant, $burger);
    app(BranchStock::class)->markOutOfStockToday($burger, $branch);

    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->assertSee(__('guest.checkout.remove'))
        ->set('payment', 'pay_at_pickup')
        ->call('place')
        ->assertSet('problem', __('ordering.errors.cart_changed'));

    expect(Order::withoutTenancy()->count())->toBe(0);
});

it('only offers a scheduled time while the branch is closed', function () {
    [$restaurant, $branch, $burger] = guestShop();
    signInGuest($restaurant, regular($restaurant));
    bagWith($restaurant, $burger);
    app(UpdateOrderingStatus::class)->pause($branch, 30);

    $page = Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->assertSet('timing', 'scheduled')
        ->assertSee(__('guest.timing.scheduled'));

    // Forcing "now" from the browser is still refused by the server.
    $page->set('timing', 'now')->set('payment', 'pay_at_pickup')->call('place');

    expect($page->get('problem'))->not->toBeNull()
        ->and(Order::withoutTenancy()->count())->toBe(0);
});

it('offers the next free time when the kitchen is fully booked', function () {
    [$restaurant, $branch, $burger] = guestShop();
    $branch->forceFill(['capacity_per_slot' => 1])->save();
    $other = regular($restaurant, '+97455550099');
    signInGuest($restaurant, $other);
    bagWith($restaurant, $burger);
    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])->set('payment', 'pay_at_pickup')->call('place');

    bagWith($restaurant, $burger);
    $page = Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->set('payment', 'pay_at_pickup')
        ->call('place');

    expect($page->get('problem'))->toContain(Str::before(__('ordering.errors.capacity'), ':time'))
        ->and($page->get('suggestedAt'))->not->toBeNull();

    $page->call('useSuggestedTime')->assertSet('timing', 'scheduled')->call('place');

    expect(Order::withoutTenancy()->count())->toBe(2)
        ->and(Order::withoutTenancy()->latest('id')->first()->scheduled_for)->not->toBeNull();
});

it('enforces code limits through the sign-in form', function () {
    $this->freezeTime();
    [$restaurant, $branch, $burger] = guestShop();
    bagWith($restaurant, $burger);

    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])
        ->set('phone', '55551234')
        ->call('sendCode')
        ->call('changePhone')
        ->assertSet('phone', '5555 1234')
        ->call('sendCode')
        ->assertSet('codeSent', true)
        ->assertSet('otpError', __('customers.otp.errors.wait', ['seconds' => 30]))
        ->set('code', '000000')
        ->call('verifyCode')
        ->assertSet('otpError', fn ($error) => $error !== null);

    expect(Customer::withoutTenancy()->count())->toBe(0);
});

it('sends card payments to the gateway and starts cooking once paid', function () {
    [$restaurant, $branch, $burger] = guestShop();
    signInGuest($restaurant, regular($restaurant));
    bagWith($restaurant, $burger);

    $page = Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])->set('payment', 'card')->call('place');
    $order = Order::withoutTenancy()->sole();
    $payment = $order->payment;

    expect($order->status)->toBe(OrderStatus::PendingPayment);
    $page->assertRedirect($payment->redirect_url);

    $this->get($payment->redirect_url)->assertOk()->assertSee(__('guest.pay.approve'));
    $this->post(route('payments.fake.decide', $payment->reference), ['decision' => 'approve', 'return' => 'https://evil.example/steal'])
        ->assertRedirect(route('customer.order', [$restaurant, $order]));

    expect($order->fresh()->status)->toBe(OrderStatus::Received)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('lets the guest cancel within the window and say they are on the way', function () {
    [$restaurant, $branch, $burger] = guestShop();
    signInGuest($restaurant, regular($restaurant));
    bagWith($restaurant, $burger);
    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])->set('payment', 'pay_at_pickup')->set('timing', 'on_the_way')->call('place');
    $order = Order::withoutTenancy()->sole();

    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $order])
        ->assertSee($order->pickup_code)
        ->assertSee(__('guest.order.on_the_way'))
        ->call('onTheWay')
        ->assertDontSee(__('guest.order.on_the_way_hint', ['minutes' => $order->prep_minutes]));

    expect($order->fresh()->prepare_at)->not->toBeNull();

    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $order])->call('cancel');
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    $this->travel(5)->minutes();
    bagWith($restaurant, $burger);
    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])->set('payment', 'pay_at_pickup')->call('place');
    $late = Order::withoutTenancy()->latest('id')->first();
    $this->travel(3)->minutes();

    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $late])->call('cancel')->assertDispatched('toast');
    expect($late->fresh()->status)->toBe(OrderStatus::Received);
});

it('keeps guests, orders and bags apart between restaurants', function () {
    [$alpha, $alphaBranch, $alphaBurger] = guestShop('alpha');
    [$beta, $betaBranch] = guestShop('beta');
    $sara = regular($alpha);
    signInGuest($alpha, $sara);
    bagWith($alpha, $alphaBurger);
    Livewire::test(Checkout::class, ['restaurant' => $alpha, 'branch' => $alphaBranch->code])->set('payment', 'pay_at_pickup')->call('place');
    $order = Order::withoutTenancy()->sole();

    // Signed in at Alpha means nothing at Beta.
    Livewire::test(Orders::class, ['restaurant' => $beta])->assertSee(__('guest.orders.signed_out'));
    Livewire::test(Checkout::class, ['restaurant' => $beta, 'branch' => $betaBranch->code])->assertSee(__('guest.checkout.empty_title'));

    // Alpha's order is not reachable under Beta, and Alpha's branch is not Beta's.
    $this->get(route('customer.order', [$beta, $order]))->assertNotFound();
    $pearl = app(CreateBranch::class)->handle($alpha, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    $this->get("/beta/{$pearl->code}/checkout")->assertNotFound();
    $this->get("/alpha/{$pearl->code}/checkout")->assertOk();

    // Another guest with the share link sees progress, never the pickup code or actions.
    session()->flush();
    signInGuest($alpha, regular($alpha, '+97455550777'));
    Livewire::test(OrderPage::class, ['restaurant' => $alpha, 'order' => $order])
        ->assertSee($order->status->getLabel())
        ->assertDontSee(__('guest.order.pickup_code'))
        ->assertDontSee(__('guest.order.cancel'))
        ->call('cancel')
        ->assertForbidden();

    $this->get(route('customer.receipt', [$alpha, $order]))->assertForbidden();
    expect($order->fresh()->status)->toBe(OrderStatus::Received);
});

it('manages cars and signed-in devices from the account page', function () {
    [$restaurant] = guestShop();
    $customer = regular($restaurant);
    signInGuest($restaurant, $customer);
    $elsewhere = CustomerSession::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $customer->id, 'token_hash' => hash('sha256', 'other'), 'user_agent' => 'iPhone', 'last_active_at' => now()->subDay()]);

    Livewire::test(Account::class, ['restaurant' => $restaurant])
        ->assertSee('123456')
        ->set('addingVehicle', true)
        ->set('plate', 'xy 99')
        ->call('addVehicle')
        ->assertHasNoErrors()
        ->call('revokeDevice', $elsewhere->id)
        ->set('name', 'Sara A.')
        ->set('birthday', '1994-03-02')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($customer->vehicles()->pluck('plate')->all())->toContain('XY 99')
        ->and($elsewhere->fresh()->revoked_at)->not->toBeNull()
        ->and($customer->fresh()->name)->toBe('Sara A.');

    // Someone else's car can't be touched.
    $stranger = regular($restaurant, '+97455550888');
    Livewire::test(Account::class, ['restaurant' => $restaurant])->call('deleteVehicle', $stranger->vehicles()->value('id'))->assertNotFound();
});

it('renders every guest page in both languages without errors', function (string $locale) {
    [$restaurant, $branch, $burger] = guestShop();
    $customer = regular($restaurant);
    signInGuest($restaurant, $customer);
    bagWith($restaurant, $burger);
    Livewire::test(Checkout::class, ['restaurant' => $restaurant, 'branch' => $branch->code])->set('payment', 'pay_at_pickup')->call('place');
    $order = Order::withoutTenancy()->sole();

    $this->withSession(session()->all() + ['locale' => $locale])->get('/masar')->assertOk()->assertSee('lang="'.$locale.'"', false);
    $this->get("/masar/{$branch->code}")->assertOk()->assertSee($burger->getTranslation('name', $locale));
    $this->get("/masar/{$branch->code}?item={$burger->id}")->assertOk()->assertSee(__('guest.menu.add'));
    $this->get("/masar/{$branch->code}/checkout")->assertOk();
    $this->get('/masar/orders')->assertOk()->assertSee($order->displayNumber());
    $this->get(route('customer.order', [$restaurant, $order]))->assertOk()->assertSee($order->pickup_code);
    $this->get(route('customer.receipt', [$restaurant, $order]))->assertOk();
    $this->get('/masar/account')->assertOk();
    $this->get('/masar/sign-in')->assertRedirect();
    $this->get(route('short.order', strtolower($order->short_code)))->assertRedirect(route('customer.order', [$restaurant, $order]));
})->with(['en', 'ar']);
