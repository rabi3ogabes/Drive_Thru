<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Integrations\Actions\SaveIntegration;
use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Pos\GenericPosDriver;
use App\Domain\Integrations\Webhooks\WebhookSignature;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\Integrations;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    config(['integrations.webhooks.block_private_networks' => false]);
});

function posRestaurant(bool $enabled = true): array
{
    $restaurant = restaurant(slug: 'masar');
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    app(SaveIntegration::class)->handle($restaurant, IntegrationKind::Pos, GenericPosDriver::NAME, Integration::LIVE, $enabled, [
        'order_url' => 'https://pos.example.com/orders',
        'api_key' => 'pos-secret-key',
    ], [], null);

    return [$restaurant, $branch];
}

function posOrder($branch): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ));
}

it('sends each accepted order to the POS, signed, and keeps the POS reference', function () {
    Http::fake(['pos.example.com/*' => Http::response(['reference' => 'POS-7781'])]);
    [, $branch] = posRestaurant();

    $order = posOrder($branch);

    Http::assertSent(function (Request $request) use ($order) {
        return $request->url() === 'https://pos.example.com/orders'
            && $request->hasHeader('Authorization', 'Bearer pos-secret-key')
            && WebhookSignature::verify('pos-secret-key', $request->body(), $request->header(WebhookSignature::HEADER)[0])
            && $request['id'] === 'order-'.$order->id
            && $request['order']['id'] === $order->id;
    });
    expect($order->fresh())->pos_reference->toBe('POS-7781')->pos_synced_at->not->toBeNull()->pos_error->toBeNull();
});

it('records why an order did not reach the POS', function () {
    Http::fake(['pos.example.com/*' => Http::response(['error' => 'unknown branch'], 422)]);
    [, $branch] = posRestaurant();

    $order = posOrder($branch);

    expect($order->fresh())->pos_synced_at->toBeNull()->pos_error->toBe('HTTP 422');
});

it('sends nothing when the POS connection is off', function () {
    Http::fake();
    [, $branch] = posRestaurant(enabled: false);

    posOrder($branch);

    Http::assertNothingSent();
});

it('lets the owner connect a POS and test it from the integrations page', function () {
    Http::fake(['pos.example.com/*' => Http::response(['ok' => true])]);
    $restaurant = restaurant(slug: 'masar');
    $this->actingAs(ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    Livewire::test(Integrations::class)
        ->fillForm(['pos' => ['enabled' => true, 'order_url' => 'https://pos.example.com/orders', 'api_key' => 'pos-secret-key']])
        ->call('save')
        ->assertHasNoFormErrors()
        ->call('testPos');

    $integration = Integration::withoutTenancy()->where('kind', IntegrationKind::Pos->value)->sole();
    expect($integration)->is_enabled->toBeTrue()->verified_at->not->toBeNull()
        ->and($integration->credential('api_key'))->toBe('pos-secret-key');
    Http::assertSent(fn (Request $request) => $request['type'] === 'ping');
});
