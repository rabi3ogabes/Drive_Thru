<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Branches\Models\Device;
use App\Models\User;
use App\Policies\Concerns\ChecksRestaurantPermission;

/** Paired screens: managed by whoever runs the branch they belong to. */
final class DevicePolicy
{
    use ChecksRestaurantPermission;

    public function viewAny(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageBranches, P::OperateBranch]);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Device $device): bool
    {
        return $this->inWorkspace($device->restaurant_id)
            && ($this->canInWorkspace($user, [P::ManageBranches])
                || ($this->canInWorkspace($user, [P::OperateBranch]) && $this->covers($user, $device->branch_id, $device->restaurant_id)));
    }

    public function delete(User $user, Device $device): bool
    {
        return $this->update($user, $device);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
