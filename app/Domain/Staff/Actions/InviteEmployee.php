<?php

declare(strict_types=1);

namespace App\Domain\Staff\Actions;

use App\Domain\Access\Actions\InviteUser;
use App\Domain\Access\Models\Position;
use App\Domain\Billing\Exceptions\PlanLimitReached;
use App\Domain\Billing\PlanLimits;
use App\Domain\Branches\Models\Branch;
use App\Domain\Staff\Data\EmployeeData;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Staff\StaffGuard;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds a person to the restaurant team in a position and branches, then
 * sends them a link to choose their own password. The owner never sees or
 * sets anyone's password. Returns the employee and the link (for WhatsApp).
 */
final class InviteEmployee
{
    public function __construct(
        private readonly StaffGuard $guard,
        private readonly PlanLimits $limits,
        private readonly InviteUser $inviteUser,
    ) {}

    /**
     * @return array{employee: Employee, url: string}
     *
     * @throws AuthorizationException|ValidationException|PlanLimitReached
     */
    public function handle(Restaurant $restaurant, EmployeeData $data, User $actor, bool $sendEmail = true): array
    {
        if (! $this->guard->canManageStaff($actor, $restaurant)) {
            throw new AuthorizationException(__('staff.errors.manage'));
        }

        $position = $this->position($restaurant, $data->positionId);
        $branchIds = $this->branchIds($restaurant, $data);

        $this->guard->ensureCanGrant($actor, $restaurant, $position->permissions);
        $this->guard->ensureCanAssignBranches($actor, $restaurant, $branchIds, $data->allBranches);

        $email = mb_strtolower(trim($data->email));

        $employee = DB::transaction(function () use ($restaurant, $data, $email, $actor, $position, $branchIds): Employee {
            Restaurant::query()->whereKey($restaurant->id)->lockForUpdate()->first();
            $this->limits->ensure($restaurant, PlanLimits::EMPLOYEES);

            $user = User::query()->where('email', $email)->first();

            if ($user !== null && Employee::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['email' => __('staff.errors.already_member')]);
            }

            if ($user === null) {
                $user = User::create([
                    'name' => $data->name,
                    'email' => $email,
                    'phone' => $data->phone,
                    'locale' => $restaurant->default_locale,
                    // Unusable until they choose their own through the invitation.
                    'password' => Str::random(48),
                ]);
            }

            $employee = Employee::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $user->id,
                'position_id' => $position->id,
                'all_branches' => $data->allBranches,
                'status' => EmployeeStatus::Invited,
                'invited_at' => now(),
            ]);

            $employee->branches()->sync($data->allBranches ? [] : $branchIds);

            activity()->performedOn($employee)->causedBy($actor)->event('employee.invited')
                ->withProperties(['position' => $position->key ?? $position->id, 'branches' => $data->allBranches ? 'all' : $branchIds])
                ->log('employee.invited');

            return $employee;
        });

        $url = $this->inviteUser->handle($employee->user, $actor, $sendEmail, context: $restaurant->name);

        return ['employee' => $employee, 'url' => $url];
    }

    /**
     * @throws ValidationException
     */
    public function position(Restaurant $restaurant, ?int $positionId): Position
    {
        $position = $positionId === null ? null : Position::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->find($positionId);

        if ($position === null) {
            throw ValidationException::withMessages(['position_id' => __('staff.errors.position')]);
        }

        return $position;
    }

    /**
     * @return list<int>
     *
     * @throws ValidationException
     */
    public function branchIds(Restaurant $restaurant, EmployeeData $data): array
    {
        if ($data->allBranches) {
            return [];
        }

        $wanted = array_values(array_unique($data->branchIds));

        $ids = array_values(array_map(
            intval(...),
            Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->whereKey($wanted)->pluck('id')->all(),
        ));

        if ($ids === [] || count($ids) !== count($wanted)) {
            throw ValidationException::withMessages(['branch_ids' => __('staff.errors.branches_required')]);
        }

        return $ids;
    }
}
