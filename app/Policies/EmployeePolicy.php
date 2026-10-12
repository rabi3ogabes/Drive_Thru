<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Staff\Models\Employee;
use App\Domain\Staff\StaffGuard;
use App\Models\User;
use App\Policies\Concerns\ChecksRestaurantPermission;

/**
 * The team list is for people who manage staff. Changing someone also needs
 * the escalation guard: never the owner, never yourself, never someone more
 * powerful or outside your branches.
 */
final class EmployeePolicy
{
    use ChecksRestaurantPermission;

    public function __construct(private readonly StaffGuard $guard) {}

    public function viewAny(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageStaff]) || $this->canBrowseFromConsole($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->inWorkspace($employee->restaurant_id) && $this->canInWorkspace($user, [P::ManageStaff]);
    }

    public function create(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageStaff]);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->inWorkspace($employee->restaurant_id) && $this->guard->canManage($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
