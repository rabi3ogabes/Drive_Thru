<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Security\Models\SecurityAlert;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

final class SecurityAlertPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ViewSecurity);
    }

    public function view(User $user, SecurityAlert $alert): bool
    {
        return $this->allowed($user, PlatformPermission::ViewSecurity);
    }

    public function update(User $user, SecurityAlert $alert): bool
    {
        return $this->allowed($user, PlatformPermission::ViewSecurity);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, SecurityAlert $alert): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
