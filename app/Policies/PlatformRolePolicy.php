<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

final class PlatformRolePolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff);
    }

    public function view(User $user, PlatformRole $role): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff);
    }

    /** Nobody edits the role they hold themselves (no self-escalation). */
    public function update(User $user, PlatformRole $role): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff)
            && ($user->isSuperAdmin() || $user->platform_role_id !== $role->id);
    }

    public function delete(User $user, PlatformRole $role): bool
    {
        return $this->update($user, $role) && ! $role->is_system && ! $role->users()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
