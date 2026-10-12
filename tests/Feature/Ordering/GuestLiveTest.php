<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Lane;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TrackGuestLocation;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Events\OrderBroadcast;
use App\Domain\Ordering\Models\Order;
use App\Livewire\Customer\OrderStatus as OrderPage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Lusail Marina-ish. 0.009° of latitude ≈ 1 km.
const BRANCH_LAT = 25.4000;
const BRANCH_LNG = 51.5000;

function trackedBranch(array $settings = []): Branch
{
    $branch = app(CreateBranch::class)->handle(restaurant(['en' => 'Masar '.Str::random(5), 'ar' => 'مسار']), ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
    $branch->forceFill(array_merge(['latitude' => BRANCH_LAT, 'longitude' => BRANCH_LNG, 'arrival_radius_m' => 60, 'approach_radius_m' => 1500, 'location_ping_seconds' => 20, 'avg_speed_kmh' => 36], $settings))->save();

    return $branch->fresh();
}

function trackedOrder(Branch $branch, OrderTiming $timing = OrderTiming::Now): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer,
        branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup,
        idempotencyKey: (string) Str::uuid(),
        timing: $timing,
    ));
}

function signInOwnerOf(Order $order): void
{
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $order->restaurant_id, 'customer_id' => $order->customer_id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$order->restaurant_id}", $token);
    app()->forgetScopedInstances();
}

function tracker(): TrackGuestLocation
{
    return app(TrackGuestLocation::class);
}

it('follows a guest who shares their location: prepare now on approach, arrived inside the radius', function () {
    $branch = trackedBranch();
    $order = trackedOrder($branch, OrderTiming::OnTheWay);
    expect($order->status)->toBe(OrderStatus::Scheduled);

    // 10 km out: distance and ETA, nothing else yet.
    $far = tracker()->ping($order, BRANCH_LAT + 0.09, BRANCH_LNG);
    expect($far['distance_m'])->toBeBetween(9900, 10100)
        ->and($far['eta_minutes'])->toBe(17)
        ->and($order->fresh())->status->toBe(OrderStatus::Scheduled)->location_consent_at->not->toBeNull();

    // A ping sooner than the branch's interval changes nothing.
    tracker()->ping($order->fresh(), BRANCH_LAT + 0.0045, BRANCH_LNG);
    expect($order->fresh()->last_distance_m)->toBe($far['distance_m']);

    // 1 km out: inside the approach radius, the held order goes to the kitchen.
    $this->travel(25)->seconds();
    tracker()->ping($order->fresh(), BRANCH_LAT + 0.009, BRANCH_LNG);
    expect($order->fresh())->status->toBe(OrderStatus::Received)->approach_alerted_at->not->toBeNull()->on_the_way_at->not->toBeNull()
        ->and($order->fresh()->history()->reorder('id', 'desc')->first()->note)->toBe('approaching');

    // 30 m: arrived automatically.
    $this->travel(25)->seconds();
    $here = tracker()->ping($order->fresh(), BRANCH_LAT + 0.00027, BRANCH_LNG);
    expect($here['arrived'])->toBeTrue()
        ->and($order->fresh())->arrived_at->not->toBeNull()->arrival_source->toBe('auto');
});

it('respects the branch: no auto-arrival when it is off, nothing when tracking is off', function () {
    $manual = trackedBranch(['auto_arrival' => false]);
    $order = trackedOrder($manual);
    tracker()->ping($order, BRANCH_LAT, BRANCH_LNG);
    expect($order->fresh()->arrived_at)->toBeNull()->and($order->fresh()->last_distance_m)->toBe(0);

    $off = trackedBranch(['tracking_enabled' => false]);
    $other = trackedOrder($off);
    expect(tracker()->ping($other, BRANCH_LAT, BRANCH_LNG)['distance_m'])->toBeNull()
        ->and($other->fresh()->last_location_at)->toBeNull();
});

it('records when a guest declines location sharing', function () {
    $order = trackedOrder(trackedBranch());
    tracker()->denied($order);

    expect($order->fresh()->location_denied_at)->not->toBeNull();
});

it('lets the guest say "I\'m here" with their lane and share location from the order page', function () {
    $branch = trackedBranch();
    $lane = Lane::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'branch_id' => $branch->id, 'name' => ['en' => 'Lane 2', 'ar' => 'المسار 2']]);
    $order = trackedOrder($branch);
    signInOwnerOf($order);

    $page = Livewire::test(OrderPage::class, ['restaurant' => $branch->restaurant, 'order' => $order])
        ->assertSee(__('guest.order.im_here'))
        ->assertSee(__('guest.order.share_hint'))
        ->assertSee('data-live-channel="order.'.$order->short_code.'"', false);

    expect($page->instance()->ping(BRANCH_LAT + 0.018, BRANCH_LNG)['distance_m'])->toBeBetween(1950, 2050);

    $page->call('imHere', $lane->id)->assertDispatched('toast');
    expect($order->fresh())->arrived_at->not->toBeNull()->lane_id->toBe($lane->id)->arrival_source->toBe('guest');

    $page->call('$refresh');
    $page->assertSee(__('guest.order.here_at', ['lane' => $lane->fresh()->name]));
});

it('announces status changes on the page and shows the Ready alert to the guest only', function () {
    $branch = trackedBranch();
    $order = trackedOrder($branch);
    signInOwnerOf($order);

    $page = Livewire::test(OrderPage::class, ['restaurant' => $branch->restaurant, 'order' => $order])->assertNotDispatched('order-status');

    app(TransitionOrder::class)->handle($order, OrderStatus::Ready, TransitionOrder::STAFF, 1);
    $page->call('$refresh')
        ->assertDispatched('order-status', status: 'ready')
        ->assertSee('role="alertdialog"', false)
        ->assertSee($order->pickup_code);

    // Someone with the share link: progress only, no live channel, no alert, no arrival controls.
    session()->flush();
    app()->forgetScopedInstances();
    Livewire::test(OrderPage::class, ['restaurant' => $branch->restaurant, 'order' => $order])
        ->assertDontSee('role="alertdialog"', false)
        ->assertDontSee(__('guest.order.im_here'))
        ->assertDontSee('data-live-channel', false)
        ->call('imHere')
        ->assertForbidden();
});

it('lets a signed staff link follow the order live', function () {
    $order = trackedOrder(trackedBranch());
    $link = URL::temporarySignedRoute('short.order.staff', now()->addHour(), ['code' => $order->short_code]);

    $this->get($link)->assertRedirect(route('customer.order', [$order->restaurant, $order]));
    $this->get(route('customer.order', [$order->restaurant, $order]))->assertOk()->assertSee('data-live-channel="order.'.$order->short_code.'"', false);

    $this->get(route('short.order.staff', ['code' => $order->short_code]))->assertForbidden();
});

it('broadcasts arrival and approach so screens update at once', function () {
    Event::fake([OrderBroadcast::class]);
    $branch = trackedBranch();
    $order = trackedOrder($branch);

    tracker()->ping($order, BRANCH_LAT + 0.009, BRANCH_LNG);
    $this->travel(25)->seconds();
    tracker()->ping($order->fresh(), BRANCH_LAT, BRANCH_LNG);

    Event::assertDispatched(OrderBroadcast::class, fn (OrderBroadcast $e) => $e->what === 'approaching');
    Event::assertDispatched(OrderBroadcast::class, fn (OrderBroadcast $e) => $e->what === 'arrived');
});
