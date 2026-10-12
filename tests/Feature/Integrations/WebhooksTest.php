<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Integrations\Actions\SaveWebhookEndpoint;
use App\Domain\Integrations\Api\ApiScope;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\Webhooks\DispatchWebhook;
use App\Domain\Integrations\Webhooks\SafeUrl;
use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Domain\Integrations\Webhooks\WebhookSignature;
use App\Domain\Menu\Models\Product;
use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\OpsAlert;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\Webhooks\Pages\ManageWebhooks;
use App\Filament\App\Resources\Webhooks\Pages\ViewWebhook;
use App\Filament\App\Resources\Webhooks\RelationManagers\DeliveriesRelationManager;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['integrations.webhooks.block_private_networks' => false]);
});

function hookRestaurant(): array
{
    $restaurant = restaurant(slug: 'masar');
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);

    return [$restaurant, $branch, ownerOf($restaurant)];
}

function hookEndpoint(Restaurant $restaurant, User $owner, array $events = ['order.created', 'order.status_changed']): array
{
    return app(SaveWebhookEndpoint::class)->handle($restaurant, ['url' => 'https://pos.example.com/hooks', 'events' => $events], $owner);
}

function hookOrder(Branch $branch): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500, 'name' => ['en' => 'Burger', 'ar' => 'برجر']]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 2]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ));
}

it('sends signed order webhooks a receiver can verify, without the guest\'s phone', function () {
    Http::fake(['pos.example.com/*' => Http::response('ok')]);
    [$restaurant, $branch, $owner] = hookRestaurant();
    [, $secret] = hookEndpoint($restaurant, $owner);

    $order = hookOrder($branch);
    app(TransitionOrder::class)->handle($order, OrderStatus::Preparing, 'staff:1');

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request) use ($secret, $order) {
        $body = $request->body();
        $payload = json_decode($body, true);

        return $request->url() === 'https://pos.example.com/hooks'
            && $request->hasHeader('X-DTP-Event', 'order.created')
            && WebhookSignature::verify($secret, $body, $request->header(WebhookSignature::HEADER)[0])
            && $payload['type'] === 'order.created'
            && $payload['data']['order']['id'] === $order->id
            && $payload['data']['order']['items'][0]['name'] === ['en' => 'Burger', 'ar' => 'برجر']
            && $payload['data']['order']['totals']['total_minor'] === $order->total_minor
            && ! str_contains($body, '+97455550001');
    });
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-DTP-Event', 'order.status_changed')
        && json_decode($request->body(), true)['data']['previous_status'] === 'received');

    expect(WebhookDelivery::withoutTenancy()->where('status', WebhookDelivery::DELIVERED)->count())->toBe(2);
});

it('only sends the events an endpoint subscribed to, and nothing to other restaurants', function () {
    Http::fake(['*' => Http::response('ok')]);
    [$restaurant, $branch, $owner] = hookRestaurant();
    hookEndpoint($restaurant, $owner, ['payment.refunded']);
    $other = restaurant(slug: 'other');
    hookEndpoint($other, ownerOf($other), ['order.created']);

    hookOrder($branch);

    Http::assertNothingSent();
});

it('retries with backoff, then fails, and replays with the same event id', function () {
    Http::fake(['pos.example.com/*' => Http::sequence()->push('down', 503)->push('down', 500)->push('ok', 200)->push('ok', 200)]);
    [$restaurant, $branch, $owner] = hookRestaurant();
    hookEndpoint($restaurant, $owner);

    hookOrder($branch);
    $delivery = WebhookDelivery::withoutTenancy()->sole();
    expect($delivery)->status->toBe(WebhookDelivery::PENDING)->attempts->toBe(1)->response_status->toBe(503)
        ->and($delivery->next_attempt_at->diffInSeconds(now()->addMinute(), true))->toBeLessThan(5);

    $this->artisan('webhooks:retry')->expectsOutputToContain('Queued 0');
    $this->travel(61)->seconds();
    $this->artisan('webhooks:retry');
    expect($delivery->fresh())->attempts->toBe(2)->status->toBe(WebhookDelivery::PENDING);

    $this->travel(6)->minutes();
    $this->artisan('webhooks:retry');
    expect($delivery->fresh())->status->toBe(WebhookDelivery::DELIVERED)->attempts->toBe(3);

    app(DispatchWebhook::class)->replay($delivery->fresh());
    expect(WebhookDelivery::withoutTenancy()->count())->toBe(2)
        ->and(WebhookDelivery::withoutTenancy()->pluck('event_id')->unique())->toHaveCount(1);
});

it('gives up after the last retry and switches off an endpoint that only fails', function () {
    Notification::fake();
    Http::fake(['pos.example.com/*' => Http::response('nope', 500)]);
    [$restaurant, $branch, $owner] = hookRestaurant();
    [$endpoint] = hookEndpoint($restaurant, $owner);
    $endpoint->forceFill(['created_at' => now()->subDays(5)])->save();

    hookOrder($branch);
    $delivery = WebhookDelivery::withoutTenancy()->sole();

    foreach (WebhookDelivery::BACKOFF_MINUTES as $minutes) {
        $this->travel($minutes + 1)->minutes();
        $this->artisan('webhooks:retry');
    }

    expect($delivery->fresh())->status->toBe(WebhookDelivery::FAILED)->attempts->toBe(count(WebhookDelivery::BACKOFF_MINUTES) + 1)
        ->and($endpoint->fresh())->is_active->toBeFalse()->disabled_at->not->toBeNull();
    Notification::assertSentTo($owner, fn (OpsAlert $alert) => $alert->kind === AlertKind::WebhookDisabled);
});

it('refuses webhook addresses on private networks or without HTTPS', function () {
    config(['integrations.webhooks.block_private_networks' => true]);
    $safe = app(SafeUrl::class);

    expect($safe->allows('http://pos.example.com/hooks'))->toBeFalse()
        ->and($safe->allows('https://127.0.0.1/hooks'))->toBeFalse()
        ->and($safe->allows('https://10.0.0.5/hooks'))->toBeFalse()
        ->and($safe->allows('https://169.254.169.254/latest/meta-data'))->toBeFalse()
        ->and($safe->allows('https://[::1]/hooks'))->toBeFalse()
        ->and($safe->allows('https://user:pass@8.8.8.8/hooks'))->toBeFalse()
        ->and($safe->allows('https://8.8.8.8/hooks'))->toBeTrue();

    [$restaurant, , $owner] = hookRestaurant();
    expect(fn () => app(SaveWebhookEndpoint::class)->handle($restaurant, ['url' => 'https://192.168.1.10/x', 'events' => ['order.created']], $owner))
        ->toThrow(ValidationException::class);
});

it('rejects stale or tampered signatures', function () {
    $header = WebhookSignature::header('whsec_test', '{"a":1}', 1_700_000_000);

    expect(WebhookSignature::verify('whsec_test', '{"a":1}', $header, 1_700_000_100))->toBeTrue()
        ->and(WebhookSignature::verify('whsec_test', '{"a":2}', $header, 1_700_000_100))->toBeFalse()
        ->and(WebhookSignature::verify('whsec_other', '{"a":1}', $header, 1_700_000_100))->toBeFalse()
        ->and(WebhookSignature::verify('whsec_test', '{"a":1}', $header, 1_700_000_000 + WebhookSignature::TOLERANCE_SECONDS + 1))->toBeFalse();
});

it('lets the owner add a webhook, see its secret once, send a test and replay deliveries', function () {
    Http::fake(['pos.example.com/*' => Http::response('ok')]);
    [$restaurant, , $owner] = hookRestaurant();
    $this->actingAs($owner);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    $page = Livewire::test(ManageWebhooks::class)
        ->mountAction('addWebhook')
        ->fillForm(['url' => 'https://pos.example.com/hooks', 'description' => 'Kitchen POS', 'events' => [WebhookEvent::OrderCreated->value]])
        ->callMountedAction()
        ->assertActionMounted('showSecret');
    expect($page->get('mountedActions')[0]['arguments']['secret'])->toStartWith('whsec_');

    $endpoint = WebhookEndpoint::query()->sole();
    expect($endpoint)->events->toBe(['order.created'])->description->toBe('Kitchen POS')
        ->and($endpoint->toArray())->not->toHaveKey('secret');

    Livewire::test(ViewWebhook::class, ['record' => $endpoint->getRouteKey()])->callAction('ping');
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-DTP-Event', 'ping'));

    $delivery = WebhookDelivery::query()->sole();
    Livewire::test(DeliveriesRelationManager::class, ['ownerRecord' => $endpoint, 'pageClass' => ViewWebhook::class])
        ->assertCanSeeTableRecords([$delivery])
        ->callTableAction('replay', $delivery);
    expect(WebhookDelivery::query()->count())->toBe(2);
});

it('keeps webhooks to people with the integrations permission', function () {
    [$restaurant, , $owner] = hookRestaurant();
    $manager = employeeAt($restaurant, 'general_manager', ['all_branches' => true]);
    $this->actingAs($manager);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    $this->get(ManageWebhooks::getUrl(tenant: $restaurant))->assertForbidden();
});

it('labels every webhook event and API scope in both languages', function (string $locale) {
    app()->setLocale($locale);

    foreach (WebhookEvent::cases() as $event) {
        expect($event->getLabel())->not->toContain('integrations.')->and($event->getDescription())->not->toContain('integrations.');
    }

    foreach (ApiScope::cases() as $scope) {
        expect($scope->getLabel())->not->toContain('integrations.')->and($scope->getDescription())->not->toContain('integrations.');
    }
})->with(['ar', 'en']);
