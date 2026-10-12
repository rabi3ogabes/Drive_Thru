<?php

use App\Domain\Access\DefaultPositions;
use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Access\Models\Position;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('creates every built-in position with names in both languages', function () {
    $restaurant = restaurant();

    $positions = Position::withoutTenancy()->where('restaurant_id', $restaurant->id)->get();

    expect($positions->pluck('key')->sort()->values()->all())->toBe(collect(DefaultPositions::keys())->sort()->values()->all());

    foreach ($positions as $position) {
        expect($position->getTranslation('name', 'en'))->not->toBeEmpty()->not->toStartWith('positions.')
            ->and($position->getTranslation('name', 'ar'))->not->toBeEmpty()->not->toStartWith('positions.')
            ->and($position->is_system)->toBeTrue();
    }
});

it('gives the super admin every ability', function () {
    $admin = User::factory()->superAdmin()->create();
    $restaurant = restaurant();

    foreach (PlatformPermission::cases() as $permission) {
        expect(Gate::forUser($admin)->allows($permission->ability()))->toBeTrue();
    }

    expect(Gate::forUser($admin)->allows(RestaurantPermission::RefundPayments->ability(), $restaurant))->toBeTrue();
});

it('limits platform staff to their role', function () {
    $role = PlatformRole::factory()->granting([PlatformPermission::ViewClients])->create();
    $staff = User::factory()->create(['email' => 'support@example.com']);
    $staff->forceFill(['platform_role_id' => $role->id])->save();

    expect($staff->hasPlatformPermission(PlatformPermission::ViewClients))->toBeTrue()
        ->and($staff->hasPlatformPermission(PlatformPermission::ManageClients))->toBeFalse()
        ->and(Gate::forUser($staff)->allows(PlatformPermission::ManageBilling->ability()))->toBeFalse();
});

it('denies everything to a deactivated account', function () {
    $admin = User::factory()->superAdmin()->inactive()->create();

    expect(Gate::forUser($admin)->allows(PlatformPermission::ViewClients->ability()))->toBeFalse()
        ->and($admin->hasPlatformPermission(PlatformPermission::ViewClients))->toBeFalse();
});

it('gives the restaurant owner every restaurant permission', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);

    foreach (RestaurantPermission::cases() as $permission) {
        expect($owner->hasRestaurantPermission($restaurant, $permission))->toBeTrue();
    }
});

it('limits an employee to the permissions of their position', function (string $position, RestaurantPermission $allowed, RestaurantPermission $denied) {
    $restaurant = restaurant();
    $user = employeeAt($restaurant, $position);

    expect($user->hasRestaurantPermission($restaurant, $allowed))->toBeTrue()
        ->and($user->hasRestaurantPermission($restaurant, $denied))->toBeFalse();
})->with([
    'cashier collects payments, cannot refund' => ['cashier', RestaurantPermission::CollectPayments, RestaurantPermission::RefundPayments],
    'kitchen runs the KDS, cannot edit the menu' => ['kitchen', RestaurantPermission::OperateKitchen, RestaurantPermission::ManageMenu],
    'accountant sees finance, cannot handle orders' => ['accountant', RestaurantPermission::ViewFinance, RestaurantPermission::OperateOrders],
    'marketing runs campaigns, cannot see finance' => ['marketing', RestaurantPermission::ManageCampaigns, RestaurantPermission::ViewFinance],
    'branch manager refunds, cannot change billing' => ['branch_manager', RestaurantPermission::RefundPayments, RestaurantPermission::ManageBilling],
    'general manager edits menu, cannot change billing' => ['general_manager', RestaurantPermission::ManageMenu, RestaurantPermission::ManageBilling],
]);

it('resolves restaurant gates from the current tenant', function () {
    $restaurant = restaurant();
    $cashier = employeeAt($restaurant, 'cashier');

    app(TenantContext::class)->set($restaurant);

    expect(Gate::forUser($cashier)->allows(RestaurantPermission::CollectPayments->ability()))->toBeTrue()
        ->and(Gate::forUser($cashier)->allows(RestaurantPermission::ManageStaff->ability()))->toBeFalse();
});

it('gives a suspended employee no permissions', function () {
    $restaurant = restaurant();
    $user = employeeAt($restaurant, 'branch_manager', ['status' => EmployeeStatus::Suspended]);

    expect($user->hasRestaurantPermission($restaurant, RestaurantPermission::ViewOrders))->toBeFalse();
});

it('gives an employee nothing at another restaurant', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار']);
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء']);
    $manager = employeeAt($masar, 'general_manager');

    expect($manager->hasRestaurantPermission($masar, RestaurantPermission::ManageMenu))->toBeTrue()
        ->and($manager->hasRestaurantPermission($sahra, RestaurantPermission::ViewOrders))->toBeFalse()
        ->and(Gate::forUser($manager)->allows(RestaurantPermission::ViewOrders->ability(), $sahra))->toBeFalse();
});

it('has a label, description and group for every permission in both languages', function () {
    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);

        foreach ([...PlatformPermission::cases(), ...RestaurantPermission::cases()] as $permission) {
            expect($permission->getLabel())->not->toStartWith('permissions.')
                ->and($permission->getDescription())->not->toStartWith('permissions.')
                ->and($permission->groupLabel())->not->toStartWith('permissions.');
        }
    }
});

it('owns the membership record through the restaurant', function () {
    $restaurant = restaurant();
    $user = employeeAt($restaurant, 'cashier');

    expect($user->employments()->count())->toBe(1)
        ->and(Employee::withoutTenancy()->where('user_id', $user->id)->first()->restaurant->is($restaurant))->toBeTrue();
});
