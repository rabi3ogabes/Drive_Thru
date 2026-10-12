<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Integrations\Actions\IssueApiClient;
use App\Domain\Integrations\Models\ApiClient;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderStatusChange;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\ApiKeys\Pages\ManageApiKeys;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

function apiSetup(string $slug = 'masar'): array
{
    $restaurant = restaurant(slug: $slug);
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => 8]);

    return [$restaurant, $branch, ownerOf($restaurant)];
}

function apiKey(Restaurant $restaurant, array $scopes): string
{
    [, $key] = app(IssueApiClient::class)->handle($restaurant, 'POS', $scopes, ownerOf($restaurant));
    app(TenantContext::class)->set(null);

    return $key;
}

function apiOrder(Branch $branch): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $category = MenuCategory::withoutTenancy()->where('restaurant_id', $branch->restaurant_id)->first()
        ?? MenuCategory::withoutTenancy()->create(['restaurant_id' => $branch->restaurant_id, 'name' => ['en' => 'Burgers', 'ar' => 'برجر']]);
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'menu_category_id' => $category->id, 'price_minor' => 2500, 'name' => ['en' => 'Burger', 'ar' => 'برجر']]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ));
}

it('lists and shows this restaurant\'s orders only, with cursor pages and filters', function () {
    [$restaurant, $branch] = apiSetup();
    [, $otherBranch] = apiSetup('other');
    $mine = [apiOrder($branch), apiOrder($branch)];
    $theirs = apiOrder($otherBranch);
    $key = apiKey($restaurant, ['orders:read']);

    $page = $this->withToken($key)->getJson('/api/v1/orders?per_page=1&status=received')->assertOk();
    expect($page->json('data'))->toHaveCount(1)
        ->and($page->json('data.0.id'))->toBe($mine[1]->id)
        ->and($page->json('data.0'))->not->toHaveKey('customer.phone')
        ->and($page->json('next_cursor'))->not->toBeNull();

    $this->withToken($key)->getJson('/api/v1/orders?per_page=1&cursor='.$page->json('next_cursor'))->assertOk()->assertJsonPath('data.0.id', $mine[0]->id);
    $this->withToken($key)->getJson('/api/v1/orders/'.$theirs->id)->assertNotFound()->assertExactJson(['error' => ['code' => 'not_found', 'message' => 'Not found.']]);
    $this->withToken($key)->getJson('/api/v1/orders?status=ready')->assertOk()->assertJsonCount(0, 'data');
});

it('refuses missing, wrong, revoked or under-scoped keys', function () {
    [$restaurant, $branch] = apiSetup();
    $order = apiOrder($branch);
    $readOnly = apiKey($restaurant, ['orders:read']);

    $this->getJson('/api/v1/orders')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    $this->withToken('1|not-a-real-key')->getJson('/api/v1/orders')->assertUnauthorized();
    $this->withToken($readOnly)->postJson('/api/v1/orders/'.$order->id.'/status', ['status' => 'preparing'])->assertForbidden()->assertJsonPath('error.code', 'insufficient_scope');
    $this->withToken($readOnly)->getJson('/api/v1/branches')->assertForbidden();

    app(IssueApiClient::class)->revoke(ApiClient::withoutTenancy()->sole(), ownerOf($restaurant));
    $this->withToken($readOnly)->getJson('/api/v1/orders')->assertUnauthorized();
});

it('does not let a signed-in person use the API with their session', function () {
    [$restaurant, , $owner] = apiSetup();

    $this->actingAs($owner)->getJson('/api/v1/orders')->assertUnauthorized();
});

it('moves orders along through the desk rules and records the integration as the actor', function () {
    [$restaurant, $branch] = apiSetup();
    $order = apiOrder($branch);
    $key = apiKey($restaurant, ['orders:read', 'orders:write']);
    $client = ApiClient::withoutTenancy()->sole();

    $this->withToken($key)->postJson('/api/v1/orders/'.$order->id.'/status', ['status' => 'ready'])->assertOk()->assertJsonPath('data.status', 'ready');
    expect(OrderStatusChange::withoutTenancy()->where('order_id', $order->id)->orderByDesc('id')->first())->actor_type->toBe('integration')->actor_id->toBe($client->id);

    $this->withToken($key)->postJson('/api/v1/orders/'.$order->id.'/status', ['status' => 'picked_up'])->assertStatus(409)->assertJsonPath('error.code', 'unpaid');
    $this->withToken($key)->postJson('/api/v1/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertUnprocessable();
    $this->withToken($key)->postJson('/api/v1/orders/'.$order->id.'/status', ['status' => 'cancelled', 'reason' => 'Customer didn’t come'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->withToken($key)->postJson('/api/v1/orders/'.$order->id.'/status', ['status' => 'preparing'])->assertStatus(409);
});

it('reads the branch menu and switches a product off and on', function () {
    [$restaurant, $branch] = apiSetup();
    $order = apiOrder($branch);
    $product = Product::withoutTenancy()->sole();
    $key = apiKey($restaurant, ['menu:read', 'menu:write']);

    $this->withToken($key)->getJson('/api/v1/branches')->assertOk()->assertJsonPath('data.0.code', $branch->code);
    $menu = $this->withToken($key)->getJson('/api/v1/branches/'.$branch->code.'/menu')->assertOk();
    expect($menu->json('data.categories.0.products.0'))->toMatchArray(['id' => $product->id, 'available' => true, 'price_minor' => 2500]);

    $this->withToken($key)->putJson('/api/v1/branches/'.$branch->code.'/products/'.$product->id.'/availability', ['status' => 'out_of_stock_today'])
        ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.unavailable_reason', 'out_of_stock');
    $this->withToken($key)->putJson('/api/v1/branches/'.$branch->code.'/products/'.$product->id.'/availability', ['status' => 'available'])
        ->assertOk()->assertJsonPath('data.available', true);
    $this->withToken($key)->putJson('/api/v1/branches/'.$branch->code.'/products/'.$product->id.'/availability', ['status' => 'gone'])->assertUnprocessable();
});

it('rate limits each key', function () {
    config(['integrations.api.requests_per_minute' => 2]);
    [$restaurant] = apiSetup();
    $key = apiKey($restaurant, ['orders:read']);

    $this->withToken($key)->getJson('/api/v1/orders')->assertOk();
    $this->withToken($key)->getJson('/api/v1/orders')->assertOk();
    $this->withToken($key)->getJson('/api/v1/orders')->assertTooManyRequests()->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
});

it('lets the owner create a key shown once, and revoke it', function () {
    [$restaurant, , $owner] = apiSetup();
    $this->actingAs($owner);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    $page = Livewire::test(ManageApiKeys::class)
        ->mountAction('addKey')
        ->fillForm(['name' => 'Foodics POS', 'scopes' => ['orders:read']])
        ->callMountedAction()
        ->assertActionMounted('showKey');
    $key = $page->get('mountedActions')[0]['arguments']['key'];
    $client = ApiClient::query()->sole();

    expect($client)->name->toBe('Foodics POS')->scopes->toBe(['orders:read']);
    app(TenantContext::class)->set(null);
    $this->withToken($key)->getJson('/api/v1/orders')->assertOk();

    app(TenantContext::class)->set($restaurant);
    Livewire::test(ManageApiKeys::class)->callTableAction('revoke', $client);
    expect($client->fresh()->isActive())->toBeFalse()->and($client->tokens()->count())->toBe(0);
});

it('publishes the OpenAPI description and a readable docs page in both languages', function () {
    $this->getJson('/api/v1/openapi.json')->assertOk()->assertJsonPath('openapi', '3.1.0')->assertJsonStructure(['paths' => ['/orders', '/orders/{id}/status'], 'webhooks' => ['order.created']]);

    $this->get('/developers/api?lang=en')->assertOk()->assertSee('Developer API')->assertSee('X-DTP-Signature');
    $this->get('/developers/api?lang=ar')->assertOk()->assertSee('واجهة المطورين');
});
