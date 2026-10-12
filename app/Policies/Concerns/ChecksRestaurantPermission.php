<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;

/**
 * Policy helpers for records inside a restaurant. In the restaurant
 * workspace the current restaurant comes from the tenant context; in the
 * platform console (no tenant) staff may only look, never change.
 */
trait ChecksRestaurantPermission
{
    private function workspace(): ?Restaurant
    {
        return app(TenantContext::class)->get();
    }

    /**
     * @param  list<RestaurantPermission>  $permissions  any one of them is enough
     */
    private function canInWorkspace(User $user, array $permissions): bool
    {
        $restaurant = $this->workspace();

        if ($restaurant === null) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasRestaurantPermission($restaurant, $permission)) {
                return true;
            }
        }

        return false;
    }

    /** Listing from the platform console (read-only client pages). */
    private function canBrowseFromConsole(User $user): bool
    {
        return $this->workspace() === null && $user->hasPlatformPermission(PlatformPermission::ViewClients);
    }

    /** The record belongs to the restaurant being worked in. */
    private function inWorkspace(int $restaurantId): bool
    {
        return $this->workspace()?->getKey() === $restaurantId;
    }

    /** The user works in this branch (owners and all-branch staff work in all). */
    private function covers(User $user, Branch|int $branch, int $restaurantId): bool
    {
        if ($user->isSuperAdmin()) {
            return $user->is_active;
        }

        return $user->employmentAt($restaurantId)?->coversBranch($branch) ?? false;
    }
}
