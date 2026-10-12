<?php

use App\Domain\Access\Models\Position;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Http\Middleware\AuthenticateDevice;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
 * A restaurant created through the real action (with its system positions).
 */
function restaurant(array $name = ['en' => 'Masar Grill', 'ar' => 'مسار'], ?User $owner = null, ?string $slug = null): Restaurant
{
    return app(CreateRestaurant::class)->handle($name, owner: $owner, slug: $slug);
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
