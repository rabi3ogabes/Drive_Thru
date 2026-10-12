<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

/**
 * Platform-side access to clients. Restaurant staff reach their own workspace
 * through tenancy (User::canAccessTenant), never through this policy.
 */
final class RestaurantPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ViewClients);
    }

    public function view(User $user, Restaurant $restaurant): bool
    {
        return $this->allowed($user, PlatformPermission::ViewClients);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageClients);
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $this->allowed($user, PlatformPermission::ManageClients);
    }

    public function suspend(User $user, Restaurant $restaurant): bool
    {
        return $this->allowed($user, PlatformPermission::ManageClients);
    }

    public function impersonate(User $user, Restaurant $restaurant): bool
    {
        return $this->allowed($user, PlatformPermission::ImpersonateOwners);
    }

    public function manageBilling(User $user, Restaurant $restaurant): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling);
    }

    /** Clients are suspended or cancelled, never deleted from the console. */
    public function delete(User $user, Restaurant $restaurant): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
