<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

/**
 * Managing the platform team. Nobody can change their own access, and only a
 * super admin can touch another super admin.
 */
final class UserPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff);
    }

    public function view(User $user, User $target): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff);
    }

    public function update(User $user, User $target): bool
    {
        return $this->allowed($user, PlatformPermission::ManagePlatformStaff)
            && ! $user->is($target)
            && (! $target->isSuperAdmin() || $user->isSuperAdmin());
    }

    public function suspend(User $user, User $target): bool
    {
        return $this->update($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
