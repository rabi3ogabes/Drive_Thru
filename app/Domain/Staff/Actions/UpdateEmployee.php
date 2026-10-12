<?php

declare(strict_types=1);

namespace App\Domain\Staff\Actions;

use App\Domain\Staff\Data\EmployeeData;
use App\Domain\Staff\Models\Employee;
use App\Domain\Staff\StaffGuard;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Changes someone's position, branches or contact details. */
final class UpdateEmployee
{
    public function __construct(
        private readonly StaffGuard $guard,
        private readonly InviteEmployee $invite,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(Employee $employee, EmployeeData $data, User $actor): Employee
    {
        $restaurant = $employee->restaurant;
        $this->guard->ensureCanManage($actor, $employee);

        $position = $this->invite->position($restaurant, $data->positionId);
        $branchIds = $this->invite->branchIds($restaurant, $data);

        $this->guard->ensureCanGrant($actor, $restaurant, $position->permissions);
        $this->guard->ensureCanAssignBranches($actor, $restaurant, $branchIds, $data->allBranches);

        return DB::transaction(function () use ($employee, $data, $position, $branchIds, $actor): Employee {
            $employee->forceFill([
                'position_id' => $position->id,
                'all_branches' => $data->allBranches,
            ])->save();

            $changes = $employee->branches()->sync($data->allBranches ? [] : $branchIds);

            if (array_filter($changes) !== []) {
                activity()->performedOn($employee)->causedBy($actor)->event('employee.branches_changed')
                    ->withProperties(['branches' => $data->allBranches ? 'all' : $branchIds])
                    ->log('employee.branches_changed');
            }

            $user = $employee->user;
            $user->fill(['name' => $data->name !== '' ? $data->name : $user->name, 'phone' => $data->phone])->save();

            $employee->unsetRelation('branches');

            return $employee->refresh();
        });
    }
}
