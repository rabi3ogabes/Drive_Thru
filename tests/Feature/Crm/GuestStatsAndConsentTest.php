<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Crm\Consent;
use App\Domain\Crm\GuestStats;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Cart;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Livewire\Customer\Account;
use App\Livewire\Customer\Checkout;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => app()->setLocale('en'));

function crmGuest(?string $phone = null): Customer
{
    return Customer::withoutTenancy()->create(['restaurant_id' => restaurant()->id, 'phone' => $phone ?? '+9745555'.random_int(1000, 9999), 'name' => 'Sara']);
}

function signInCrmGuest(Customer $customer): void
{
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'customer_id' => $customer->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$customer->restaurant_id}", $token);
    app()->forgetScopedInstances();
}

it('counts picked-up orders only, with a moving 90-day window', function () {
    $guest = crmGuest();
    pastOrder($guest, 4000, now()->subDays(200));
    pastOrder($guest, 6000, now()->subDays(10));
    pastOrder($guest, 9900, now()->subDays(5), 'cancelled');

    app(GuestStats::class)->refresh($guest);

    expect($guest->fresh())
        ->picked_up_count->toBe(2)
        ->orders_90d->toBe(1)
        ->spent_90d_minor->toBe(6000)
        ->avg_order_minor->toBe(5000)
        ->and($guest->fresh()->first_order_at->toDateString())->toBe(now()->subDays(200)->toDateString())
        ->and($guest->fresh()->last_order_at->toDateString())->toBe(now()->subDays(10)->toDateString());
});

it('refreshes a guest on pickup and on cancellation, through the real order flow', function () {
    $guest = crmGuest();
    $branch = app(CreateBranch::class)->handle($guest->restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $product = Product::factory()->create(['restaurant_id' => $guest->restaurant_id, 'price_minor' => 2500]);
    $place = fn () => app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $guest->fresh(), branch: $branch, lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ));

    $order = $place();
    $transition = app(TransitionOrder::class);
    $transition->handle($order->fresh(), OrderStatus::Preparing, 'staff');
    $transition->handle($order->fresh(), OrderStatus::Ready, 'staff');
    $order->fresh()->forceFill(['payment_status' => 'paid'])->save();
    $transition->handle($order->fresh(), OrderStatus::PickedUp, 'staff');

    expect($guest->fresh()->picked_up_count)->toBe(1)->and($guest->fresh()->last_order_at)->not->toBeNull();

    app(CancelOrder::class)->byStaff($place(), 'staff', 1, 'Guest left');

    expect($guest->fresh()->picked_up_count)->toBe(1);
});

it('gives and withdraws consent with its proof, and audits both', function () {
    $guest = crmGuest();
    $consent = app(Consent::class);

    expect($guest->acceptsOffers())->toBeFalse();

    $consent->give($guest, 'checkout');
    $guest->refresh();

    expect($guest->acceptsOffers())->toBeTrue()
        ->and($guest->offers_consent_source)->toBe('checkout')
        ->and($guest->offers_consent_version)->toBe(Consent::VERSION)
        ->and($guest->wants('offers'))->toBeTrue();

    expect($consent->withdraw($guest, 'unsubscribe_link'))->toBeTrue();
    $guest->refresh();

    expect($guest->acceptsOffers())->toBeFalse()
        ->and($guest->offers_consent_at)->toBeNull()
        ->and($guest->offers_withdrawn_at)->not->toBeNull()
        ->and($guest->wants('offers'))->toBeFalse()
        ->and($consent->withdraw($guest, 'account'))->toBeFalse()
        ->and(Activity::query()->where('subject_id', $guest->id)->where('event', 'like', 'crm.%')->pluck('event')->all())->toBe(['crm.consent_given', 'crm.consent_withdrawn']);
});

it('leaves the checkout offers box unticked, and records consent only when ticked', function () {
    $guest = crmGuest();
    CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $guest->restaurant_id, 'customer_id' => $guest->id, 'plate' => '123456', 'color' => 'White', 'is_default' => true]);
    signInCrmGuest($guest);
    $branch = app(CreateBranch::class)->handle($guest->restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $product = Product::factory()->create(['restaurant_id' => $guest->restaurant_id, 'price_minor' => 2500]);
    $checkout = function () use ($guest, $branch, $product) {
        app(Cart::class)->add($guest->restaurant_id, $product->id, 1);

        return Livewire::test(Checkout::class, ['restaurant' => $guest->restaurant, 'branch' => $branch->code])->set('payment', 'pay_at_pickup');
    };

    $checkout()->assertSet('offers', false)->assertSee(__('guest.offers.opt_in'))->call('place')->assertHasNoErrors();
    expect($guest->fresh()->acceptsOffers())->toBeFalse();

    $checkout()->set('offers', true)->call('place')->assertHasNoErrors();
    expect($guest->fresh())->acceptsOffers()->toBeTrue()->offers_consent_source->toBe('checkout');

    $checkout()->assertDontSee(__('guest.offers.opt_in'));
});

it('switches offers on and off from the account page', function () {
    $guest = crmGuest();
    signInCrmGuest($guest);

    Livewire::test(Account::class, ['restaurant' => $guest->restaurant])
        ->assertSet('offers', false)
        ->set('offers', true)->call('saveProfile');
    expect($guest->fresh())->acceptsOffers()->toBeTrue()->offers_consent_source->toBe('account');

    Livewire::test(Account::class, ['restaurant' => $guest->restaurant])
        ->assertSet('offers', true)
        ->set('offers', false)->call('saveProfile');
    expect($guest->fresh()->acceptsOffers())->toBeFalse();
});
