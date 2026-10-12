<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\Product;
use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\OpsAlert;
use App\Domain\Notifications\Channels\WebPushChannel;
use App\Domain\Notifications\Channels\WhatsAppAlertChannel;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Notifications\Push\PushTransport;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Events\PaymentRefunded;
use App\Domain\Payments\Models\Refund;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\RestaurantSettings;
use App\Filament\Pages\AlertPreferences;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** Records pushes instead of sending them. */
final class RecordingPushTransport implements PushTransport
{
    /** @var list<array{endpoint: string, payload: array<string, mixed>}> */
    public array $sent = [];

    public function configured(): bool
    {
        return true;
    }

    public function send(PushSubscription $subscription, array $payload): string
    {
        $this->sent[] = ['endpoint' => $subscription->endpoint, 'payload' => $payload];

        return self::SENT;
    }
}

beforeEach(function () {
    $this->push = new RecordingPushTransport;
    app()->instance(PushTransport::class, $this->push);
    config(['messaging.log_when_unconfigured' => true]);
});

/** @return array{0: Restaurant, 1: Branch, 2: Branch} */
function alertsRestaurant(): array
{
    $restaurant = restaurant(slug: 'masar');
    $lusail = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);
    $pearl = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'The Pearl', 'ar' => 'اللؤلؤة'], 'avg_prep_minutes' => 8]);

    return [$restaurant, $lusail, $pearl];
}

function managerOf(Restaurant $restaurant, Branch $branch, array $user = []): User
{
    $manager = employeeAt($restaurant, 'branch_manager');
    $manager->forceFill($user)->save();
    Employee::withoutTenancy()->where('user_id', $manager->id)->sole()->branches()->attach($branch->id);

    return $manager->fresh();
}

function alertOrder(Branch $branch, string $phone = '+97455550001'): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(
        ['restaurant_id' => $branch->restaurant_id, 'phone' => $phone],
        ['name' => 'Sara'],
    );
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(), locale: 'en',
    ));
}

function subscribePush(User|Customer $subscriber, string $endpoint = 'https://push.example.test/1'): void
{
    PushSubscription::query()->create([
        'subscriber_type' => $subscriber->getMorphClass(),
        'subscriber_id' => $subscriber->getKey(),
        'endpoint' => $endpoint,
        'public_key' => 'BKey',
        'auth_token' => 'auth',
        'locale' => 'en',
    ]);
}

it('alerts the managers of a late order\'s branch and the owner, once, in each person\'s language', function () {
    Notification::fake();
    [$restaurant, $lusail, $pearl] = alertsRestaurant();
    $owner = ownerOf($restaurant);
    $lusailManager = managerOf($restaurant, $lusail, ['locale' => 'ar']);
    $pearlManager = managerOf($restaurant, $pearl);
    $cashier = employeeAt($restaurant, 'cashier', ['all_branches' => true]);
    $order = alertOrder($lusail);

    $this->travel(8 + 6)->minutes();
    $this->artisan('ops:alerts')->assertSuccessful();
    $this->artisan('ops:alerts')->assertSuccessful();

    Notification::assertSentToTimes($owner, OpsAlert::class, 1);
    Notification::assertSentToTimes($lusailManager, OpsAlert::class, 1);
    Notification::assertNotSentTo([$pearlManager, $cashier], OpsAlert::class);
    Notification::assertSentTo($lusailManager, function (OpsAlert $alert, array $channels, User $notifiable) use ($order, $lusail) {
        app()->setLocale($notifiable->preferredLocale());

        return $alert->kind === AlertKind::OrderDelayed
            && $alert->title() === 'الطلب '.$order->number.' متأخر'
            && str_contains($alert->body(), 'لوسيل')
            && str_contains($alert->url, '/ops/masar/'.$lusail->code.'/live')
            && $channels === ['database', 'broadcast'];
    });

    expect($order->fresh()->delay_alerted_at)->not->toBeNull();
});

it('does not alert about orders that are on time or already ready', function () {
    Notification::fake();
    [$restaurant, $lusail] = alertsRestaurant();
    ownerOf($restaurant);
    $onTime = alertOrder($lusail);
    $ready = alertOrder($lusail, '+97455550002');
    app(TransitionOrder::class)->handle($ready, OrderStatus::Preparing, 'staff:1');
    app(TransitionOrder::class)->handle($ready->fresh(), OrderStatus::Ready, 'staff:1');

    $this->travel(10)->minutes();
    $this->artisan('ops:alerts');

    Notification::assertNothingSent();
    expect($onTime->fresh()->delay_alerted_at)->toBeNull();
});

it('alerts the pickup team when a guest has waited at the window', function () {
    Notification::fake();
    [$restaurant, $lusail] = alertsRestaurant();
    $owner = ownerOf($restaurant);
    $order = alertOrder($lusail);
    $order->forceFill(['arrived_at' => now()])->save();

    $this->travel(3)->minutes();
    $this->artisan('ops:alerts');
    Notification::assertNothingSent();

    $this->travel(3)->minutes();
    $this->artisan('ops:alerts');
    Notification::assertSentTo($owner, fn (OpsAlert $alert) => $alert->kind === AlertKind::GuestWaiting && str_contains($alert->url, '/pickup'));
});

it('flags an overloaded kitchen at most every half hour, unless busy mode is already on', function () {
    Notification::fake();
    [$restaurant, $lusail] = alertsRestaurant();
    $owner = ownerOf($restaurant);
    foreach (range(1, 8) as $i) {
        alertOrder($lusail, '+9745555100'.$i);
    }

    expect(Order::withoutTenancy()->where('status', OrderStatus::Received->value)->count())->toBe(8);
    $this->artisan('ops:alerts')->expectsOutputToContain('overloaded 1');
    $this->artisan('ops:alerts');
    Notification::assertSentToTimes($owner, OpsAlert::class, 1);
    Notification::assertSentTo($owner, fn (OpsAlert $alert) => $alert->kind === AlertKind::KitchenOverloaded && $alert->params['count'] === 8);
});

it('sends push to subscribed browsers, but holds routine alerts back in quiet hours', function () {
    [$restaurant, $lusail] = alertsRestaurant();
    $owner = ownerOf($restaurant);
    subscribePush($owner);
    $alert = new OpsAlert(AlertKind::OrderDelayed, $restaurant->id, ['number' => 'A-12', 'minutes' => 6, 'branch' => ['en' => 'Lusail', 'ar' => 'لوسيل']], 'https://example.test/ops');

    expect($alert->via($owner))->toBe(['database', 'broadcast', WebPushChannel::class]);

    $restaurant->forceFill(['quiet_hours_start' => '00:00', 'quiet_hours_end' => '23:59'])->save();
    expect($alert->via($owner))->toBe(['database', 'broadcast']);

    $critical = new OpsAlert(AlertKind::RefundFailed, $restaurant->id, ['number' => 'A-12', 'amount' => '25.00', 'branch' => 'Lusail'], 'https://example.test/ops');
    expect($critical->via($owner))->toBe(['database', 'broadcast', WebPushChannel::class, 'mail']);

    $owner->forceFill(['phone' => '+97455559999', 'notification_prefs' => ['whatsapp' => true, 'email' => false]])->save();
    expect($critical->via($owner->fresh()))->toBe(['database', 'broadcast', WebPushChannel::class, WhatsAppAlertChannel::class]);
});

it('delivers a real alert to the bell and to push', function () {
    [$restaurant, $lusail] = alertsRestaurant();
    $owner = ownerOf($restaurant);
    $owner->forceFill(['locale' => 'en'])->save();
    subscribePush($owner);
    alertOrder($lusail);

    $this->travel(15)->minutes();
    $this->artisan('ops:alerts');

    $stored = $owner->notifications()->sole();
    expect($stored->data['format'])->toBe('filament')
        ->and($stored->data['title'])->toContain('running late')
        ->and($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['payload']['tag'])->toBe('alert-order_delayed');
});

it('raises a critical alert when a refund fails', function () {
    Notification::fake();
    [$restaurant, $lusail] = alertsRestaurant();
    $owner = ownerOf($restaurant);
    $order = alertOrder($lusail);
    $payment = Payment::withoutTenancy()->create([
        'restaurant_id' => $restaurant->id, 'order_id' => $order->id, 'method' => PaymentMethod::Card, 'gateway' => 'fake',
        'status' => PaymentStatus::Paid, 'amount_minor' => 2500, 'currency' => 'QAR', 'reference' => 'T-1',
    ]);
    $refund = Refund::withoutTenancy()->create([
        'restaurant_id' => $restaurant->id, 'payment_id' => $payment->id, 'amount_minor' => 2500,
        'status' => Refund::FAILED, 'reason' => 'test', 'requested_by' => 'system',
    ]);

    event(new PaymentRefunded($refund));

    Notification::assertSentTo($owner, function (OpsAlert $alert, array $channels) {
        app()->setLocale('en');

        return $alert->kind === AlertKind::RefundFailed && in_array('mail', $channels, true) && str_contains($alert->body(), '25.00');
    });
});

it('treats quiet hours that cross midnight correctly', function () {
    $restaurant = restaurant();
    $restaurant->forceFill(['timezone' => 'Asia/Qatar', 'quiet_hours_start' => '23:00', 'quiet_hours_end' => '08:00']);

    expect($restaurant->inQuietHours(new DateTimeImmutable('2026-10-11 21:30', new DateTimeZone('UTC'))))->toBeTrue() // 00:30 Doha
        ->and($restaurant->inQuietHours(new DateTimeImmutable('2026-10-11 05:30', new DateTimeZone('UTC'))))->toBeFalse() // 08:30 Doha
        ->and($restaurant->inQuietHours(new DateTimeImmutable('2026-10-11 19:59', new DateTimeZone('UTC'))))->toBeFalse(); // 22:59 Doha
});

it('lets the owner set quiet hours in settings', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    $this->actingAs($owner);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    Livewire::test(RestaurantSettings::class)
        ->fillForm(['quiet_hours_start' => '23:00', 'quiet_hours_end' => '07:30'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($restaurant->fresh())->quiet_hours_start->toBe('23:00')->quiet_hours_end->toBe('07:30');
});

it('lets each person choose how alerts reach them', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    $owner->forceFill(['phone' => null])->save();
    $this->actingAs($owner);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);

    Livewire::test(AlertPreferences::class)
        ->assertSet('prefs', ['push' => true, 'email' => true, 'whatsapp' => false])
        ->call('toggle', 'email')
        ->call('toggle', 'whatsapp')
        ->assertSet('prefs.whatsapp', false)
        ->assertSee(__('alerts.preferences.whatsapp_needs_phone'));

    expect($owner->fresh()->wantsAlertsBy('email'))->toBeFalse();

    $this->get(AlertPreferences::getUrl(tenant: $restaurant))->assertOk()->assertSee(__('alerts.preferences.device_heading'));
});

it('saves push subscriptions for staff and for signed-in guests only', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    $body = ['endpoint' => 'https://push.example.test/abc', 'keys' => ['p256dh' => 'BPublicKey_-', 'auth' => 'authSecret']];

    $this->postJson(route('push.subscribe'), $body)->assertUnauthorized();
    $this->postJson(route('push.subscribe'), $body + ['restaurant' => 'masar'])->assertUnauthorized();

    $this->actingAs($owner)->postJson(route('push.subscribe'), $body)->assertCreated();
    expect(PushSubscription::query()->sole())->subscriber_id->toBe($owner->id);

    $this->deleteJson(route('push.unsubscribe'), ['endpoint' => $body['endpoint']])->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

it('pushes "your order is ready" to a guest who allowed it', function () {
    [$restaurant, $lusail] = alertsRestaurant();
    $order = alertOrder($lusail);
    subscribePush($order->customer);

    app(TransitionOrder::class)->handle($order, OrderStatus::Preparing, 'staff:1');
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Ready, 'staff:1');

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Order '.$order->displayNumber().' is ready')
        ->and($this->push->sent[0]['payload']['body'])->toContain('Lusail');
});
