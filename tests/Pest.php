<?php

use App\Domain\Access\Models\Position;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Ordering\Models\Order;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\RestaurantService;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Http\Middleware\AuthenticateDevice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A restaurant created through the real action (with its system positions),
 * with the Drive-Thru service on, like most clients.
 *
 * @param  list<Service>  $services
 */
function restaurant(array $name = ['en' => 'Masar Grill', 'ar' => 'مسار'], ?User $owner = null, ?string $slug = null, array $services = [Service::DriveThru]): Restaurant
{
    $restaurant = app(CreateRestaurant::class)->handle($name, owner: $owner, slug: $slug);

    foreach ($services as $service) {
        withService($restaurant, $service);
    }

    return $restaurant;
}

/** Gives a restaurant a service directly (no billing, no audit). */
function withService(Restaurant $restaurant, Service $service, ServiceStatus $status = ServiceStatus::Active, int $priceMinor = 0, array $attributes = []): RestaurantService
{
    $row = RestaurantService::withoutTenancy()->updateOrCreate(
        ['restaurant_id' => $restaurant->id, 'service' => $service->value],
        array_merge(['status' => $status, 'unit_price_minor' => $priceMinor, 'started_at' => now()], $attributes),
    );
    $restaurant->unsetRelation('services');

    return $row;
}

/**
 * A user employed at the restaurant in one of its system positions.
 */
function employeeAt(Restaurant $restaurant, ?string $positionKey = null, array $attributes = []): User
{
    $user = User::factory()->create();

    Employee::factory()->create(array_merge([
        'restaurant_id' => $restaurant->id,
        'user_id' => $user->id,
        'position_id' => $positionKey === null
            ? null
            : Position::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('key', $positionKey)->value('id'),
    ], $attributes));

    return $user->fresh();
}

function ownerOf(Restaurant $restaurant): User
{
    return employeeAt($restaurant, attributes: ['is_owner' => true]);
}

/** Pairs a screen through the real form and returns its decrypted device token. */
function pairedToken(TestCase $test, string $code): string
{
    $cookie = collect($test->post(route('screen.pair.store'), ['code' => $code])->headers->getCookies())
        ->first(fn ($c) => $c->getName() === AuthenticateDevice::COOKIE);

    return CookieValuePrefix::remove(Crypt::decryptString($cookie->getValue()));
}

/** A guest of a restaurant whose loyalty program is fully set up (values chosen for tests). */
function loyaltyCustomer(?Restaurant $restaurant = null, array $program = []): Customer
{
    $restaurant ??= restaurant();
    $restaurant->forceFill(['feature_overrides' => array_merge($restaurant->feature_overrides ?? [], ['loyalty' => true, 'promotions' => true])])->save();
    LoyaltyProgram::withoutTenancy()->updateOrCreate(['restaurant_id' => $restaurant->id], array_merge([
        'is_enabled' => true,
        'earn_points_per_unit' => 1,
        'redeem_points' => 100,
        'redeem_value_minor' => 100,
        'max_redeem_percent' => 50,
        'min_redeem_points' => 100,
        'expiry_months' => 12,
    ], $program));

    return Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+9745555'.random_int(1000, 9999), 'name' => 'Sara']);
}

/**
 * A finished order written straight to the database (no events), for stats,
 * segments and reports. Picked up at $at unless another status is given.
 *
 * @param  list<int>  $productIds  one line per product
 */
function pastOrder(Customer $customer, int $totalMinor, CarbonInterface $at, string $status = 'picked_up', array $productIds = [], array $attributes = []): Order
{
    $branch = Branch::withoutTenancy()->where('restaurant_id', $customer->restaurant_id)->first()
        ?? Branch::factory()->create(['restaurant_id' => $customer->restaurant_id]);

    $order = Order::withoutTenancy()->forceCreate(array_merge([
        'restaurant_id' => $customer->restaurant_id,
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'number' => 1000 + Order::withoutTenancy()->where('restaurant_id', $customer->restaurant_id)->count() + 1,
        'short_code' => Str::upper(Str::random(8)),
        'pickup_code' => '123456',
        'status' => $status,
        'payment_status' => 'paid',
        'payment_method' => 'pay_at_pickup',
        'currency' => 'QAR',
        'subtotal_minor' => $totalMinor,
        'total_minor' => $totalMinor,
        'prep_minutes' => 10,
        'idempotency_key' => (string) Str::uuid(),
        'placed_at' => $at,
        'picked_up_at' => $status === 'picked_up' ? $at : null,
        'created_at' => $at,
    ], $attributes));

    foreach ($productIds as $productId) {
        $order->items()->create(['restaurant_id' => $customer->restaurant_id, 'product_id' => $productId, 'name' => ['en' => 'Item', 'ar' => 'Item'], 'quantity' => 1, 'unit_price_minor' => $totalMinor, 'line_total_minor' => $totalMinor]);
    }

    return $order;
}
