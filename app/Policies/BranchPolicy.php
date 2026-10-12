<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Branches\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksRestaurantPermission;

/**
 * Branch owners and general managers manage every branch. Branch managers
 * (branches.operate) run only the branches they work in: hours, closures,
 * pause and busy mode, but not the branch details or deleting it.
 */
final class BranchPolicy
{
    use ChecksRestaurantPermission;

    public function viewAny(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageBranches, P::OperateBranch]) || $this->canBrowseFromConsole($user);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->operate($user, $branch);
    }

    public function create(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageBranches]);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->inWorkspace($branch->restaurant_id) && $this->canInWorkspace($user, [P::ManageBranches]);
    }

    /** Day-to-day control: hours, closures, pause and busy mode. */
    public function operate(User $user, Branch $branch): bool
    {
        return $this->inWorkspace($branch->restaurant_id)
            && ($this->canInWorkspace($user, [P::ManageBranches])
                || ($this->canInWorkspace($user, [P::OperateBranch]) && $this->covers($user, $branch, $branch->restaurant_id)));
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
