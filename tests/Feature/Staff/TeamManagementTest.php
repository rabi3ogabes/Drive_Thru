<?php

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Billing\Exceptions\PlanLimitReached;
use App\Domain\Billing\Models\Plan;
use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Staff\Actions\ChangeEmployeeStatus;
use App\Domain\Staff\Actions\InviteEmployee;
use App\Domain\Staff\Actions\SavePosition;
use App\Domain\Staff\Actions\SetEmployeePin;
use App\Domain\Staff\Actions\UpdateEmployee;
use App\Domain\Staff\Data\EmployeeData;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function positionId(Restaurant $restaurant, string $key): int
{
    return (int) Position::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('key', $key)->value('id');
}

function branchOf(Restaurant $restaurant, string $name = 'Lusail'): Branch
{
    return app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => $name, 'ar' => $name]]);
}

/**
 * @param  list<int>  $branchIds
 */
function invite(Restaurant $restaurant, User $actor, string $position, array $branchIds = [], bool $all = false, string $email = 'sara@example.com'): Employee
{
    return app(InviteEmployee::class)->handle($restaurant, new EmployeeData(
        name: 'Sara Ali',
        email: $email,
        phone: '+974 5000 0000',
        positionId: positionId($restaurant, $position),
        allBranches: $all,
        branchIds: $branchIds,
    ), $actor)['employee'];
}

beforeEach(fn () => Notification::fake());

it('invites a team member into a position and branches with a set-your-password link', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    [$lusail, $wakra] = [branchOf($restaurant, 'Lusail'), branchOf($restaurant, 'Wakra')];

    $result = app(InviteEmployee::class)->handle($restaurant, new EmployeeData('Sara Ali', 'Sara@Example.com', null, positionId($restaurant, 'cashier'), false, [$lusail->id, $wakra->id]), $owner);

    $employee = $result['employee']->fresh();

    expect($employee->status)->toBe(EmployeeStatus::Invited)
        ->and($employee->user->email)->toBe('sara@example.com')
        ->and($employee->branchIds())->toEqualCanonicalizing([$lusail->id, $wakra->id])
        ->and($result['url'])->toContain('/invitation/');

    Notification::assertSentTo($employee->user, InvitationNotification::class);
});

it('refuses to add someone who is already on the team', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    $branch = branchOf($restaurant);

    invite($restaurant, $owner, 'cashier', [$branch->id]);
    invite($restaurant, $owner, 'kitchen', [$branch->id]);
})->throws(ValidationException::class);

it('requires at least one branch unless the person covers all branches', function () {
    $restaurant = restaurant();

    invite($restaurant, ownerOf($restaurant), 'cashier', []);
})->throws(ValidationException::class);

it('never accepts a branch from another restaurant', function () {
    $restaurant = restaurant();
    $other = restaurant(['en' => 'Other', 'ar' => 'آخر']);
    $foreign = branchOf($other);

    invite($restaurant, ownerOf($restaurant), 'cashier', [$foreign->id]);
})->throws(ValidationException::class);

it('stops a branch manager from handing out more power than they hold', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    $branch = branchOf($restaurant);
    $manager = invite($restaurant, $owner, 'branch_manager', [$branch->id], email: 'manager@example.com');
    $manager->forceFill(['status' => EmployeeStatus::Active])->save();

    // Cashier is within a branch manager's own permissions: allowed.
    expect(invite($restaurant, $manager->user, 'cashier', [$branch->id])->exists)->toBeTrue();

    // General manager holds permissions a branch manager doesn't: refused.
    expect(fn () => invite($restaurant, $manager->user->fresh(), 'general_manager', [$branch->id], email: 'gm@example.com'))
        ->toThrow(AuthorizationException::class);
});

it('keeps a branch manager inside their own branches', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    [$mine, $theirs] = [branchOf($restaurant, 'Mine'), branchOf($restaurant, 'Theirs')];
    $manager = invite($restaurant, $owner, 'branch_manager', [$mine->id], email: 'manager@example.com');
    $manager->forceFill(['status' => EmployeeStatus::Active])->save();
    $actor = $manager->user->fresh();

    expect(fn () => invite($restaurant, $actor, 'cashier', [$theirs->id]))->toThrow(AuthorizationException::class)
        ->and(fn () => invite($restaurant, $actor, 'cashier', all: true, email: 'all@example.com'))->toThrow(AuthorizationException::class);

    $theirCashier = invite($restaurant, $owner, 'cashier', [$theirs->id], email: 'other@example.com');

    expect(fn () => app(ChangeEmployeeStatus::class)->suspend($theirCashier, $actor))->toThrow(AuthorizationException::class);
});

it('lets nobody but the platform touch the owner, and nobody suspend themselves', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    $branch = branchOf($restaurant);
    $gm = invite($restaurant, $owner, 'general_manager', all: true, email: 'gm@example.com');
    $gm->forceFill(['status' => EmployeeStatus::Active])->save();
    $ownerEmployment = $owner->employmentAt($restaurant);

    expect(fn () => app(ChangeEmployeeStatus::class)->suspend($ownerEmployment, $gm->user->fresh()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeEmployeeStatus::class)->suspend($gm->fresh(), $gm->user->fresh()))->toThrow(AuthorizationException::class);

    expect($branch->exists)->toBeTrue();
});

it('suspends, reactivates and removes a member and signs them out', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    $cashier = invite($restaurant, $owner, 'cashier', [branchOf($restaurant)->id]);
    $cashier->forceFill(['status' => EmployeeStatus::Active, 'joined_at' => now()])->save();

    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $cashier->user_id, 'payload' => '', 'last_activity' => time()]);

    app(ChangeEmployeeStatus::class)->suspend($cashier, $owner);

    expect($cashier->fresh()->status)->toBe(EmployeeStatus::Suspended)
        ->and(DB::table('sessions')->where('user_id', $cashier->user_id)->exists())->toBeFalse()
        ->and($cashier->user->fresh()->canAccessTenant($restaurant))->toBeFalse();

    app(ChangeEmployeeStatus::class)->reactivate($cashier->fresh(), $owner);
    expect($cashier->fresh()->status)->toBe(EmployeeStatus::Active);

    app(ChangeEmployeeStatus::class)->remove($cashier->fresh(), $owner);
    expect(Employee::withoutTenancy()->find($cashier->id))->toBeNull()
        ->and(User::find($cashier->user_id))->not->toBeNull();
});

it('moves a member between branches and positions', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    [$a, $b] = [branchOf($restaurant, 'A'), branchOf($restaurant, 'B')];
    $member = invite($restaurant, $owner, 'cashier', [$a->id]);

    $updated = app(UpdateEmployee::class)->handle($member, new EmployeeData('Sara A.', 'sara@example.com', null, positionId($restaurant, 'kitchen'), false, [$b->id]), $owner);

    expect($updated->position?->key)->toBe('kitchen')
        ->and($updated->branchIds())->toBe([$b->id])
        ->and($updated->user->name)->toBe('Sara A.');
});

it('sets a unique, non-obvious PIN stored only as a hash', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    $branch = branchOf($restaurant);
    $first = invite($restaurant, $owner, 'cashier', [$branch->id]);
    $second = invite($restaurant, $owner, 'kitchen', [$branch->id], email: 'chef@example.com');

    app(SetEmployeePin::class)->handle($first, '4826', $owner);

    expect($first->fresh()->pin_hash)->not->toBe('4826')->not->toBeNull()
        ->and(fn () => app(SetEmployeePin::class)->handle($second, '4826', $owner))->toThrow(ValidationException::class)
        ->and(fn () => app(SetEmployeePin::class)->handle($second, '1234', $owner))->toThrow(ValidationException::class)
        ->and(fn () => app(SetEmployeePin::class)->handle($second, '7777', $owner))->toThrow(ValidationException::class)
        ->and(fn () => app(SetEmployeePin::class)->handle($second, '12a4', $owner))->toThrow(ValidationException::class);

    app(SetEmployeePin::class)->handle($second, '590213', $owner);
    expect($second->fresh()->hasPin())->toBeTrue();
});

it('builds custom positions only from permissions the builder holds', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    $branch = branchOf($restaurant);

    $position = app(SavePosition::class)->handle($restaurant, $owner, ['en' => 'Drive-thru host', 'ar' => 'مضيف'], [], [RestaurantPermission::ViewOrders, RestaurantPermission::OperatePickup]);
    expect($position->is_system)->toBeFalse()->and($position->grants(RestaurantPermission::OperatePickup))->toBeTrue();

    $manager = invite($restaurant, $owner, 'branch_manager', [$branch->id], email: 'manager@example.com');
    $manager->forceFill(['status' => EmployeeStatus::Active])->save();

    expect(fn () => app(SavePosition::class)->handle($restaurant, $manager->user->fresh(), ['en' => 'Boss'], [], [RestaurantPermission::ManageBilling]))
        ->toThrow(AuthorizationException::class);
});

it('protects built-in positions and positions in use', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    $cashier = Position::withoutTenancy()->find(positionId($restaurant, 'cashier'));

    expect(fn () => app(SavePosition::class)->delete($cashier, $owner))->toThrow(AuthorizationException::class);

    app(SavePosition::class)->handle($restaurant, $owner, ['en' => 'Cashier'], [], [RestaurantPermission::ViewOrders], $cashier);
    expect($cashier->fresh()->permissions)->toHaveCount(1);

    app(SavePosition::class)->reset($cashier->fresh(), $owner);
    expect($cashier->fresh()->grants(RestaurantPermission::CollectPayments))->toBeTrue();

    $custom = app(SavePosition::class)->handle($restaurant, $owner, ['en' => 'Host'], [], [RestaurantPermission::ViewOrders]);
    $member = invite($restaurant, $owner, 'cashier', [branchOf($restaurant)->id]);
    $member->forceFill(['position_id' => $custom->id])->save();

    expect(fn () => app(SavePosition::class)->delete($custom, $owner))->toThrow(ValidationException::class);
});

it('enforces the plan branch and team limits with a friendly message', function () {
    $restaurant = restaurant();
    $plan = Plan::factory()->create(['limits' => ['branches' => 1, 'employees' => 1]]);
    app(StartSubscription::class)->handle($restaurant, $plan, quantity: 1);
    $owner = ownerOf($restaurant);

    $branch = branchOf($restaurant);
    invite($restaurant, $owner, 'cashier', [$branch->id]);

    expect(fn () => branchOf($restaurant, 'Second'))->toThrow(PlanLimitReached::class)
        ->and(fn () => invite($restaurant, $owner, 'kitchen', [$branch->id], email: 'two@example.com'))->toThrow(PlanLimitReached::class);

    try {
        branchOf($restaurant, 'Second');
    } catch (PlanLimitReached $e) {
        expect($e->getMessage())->not->toContain('billing.limits');
    }
});
