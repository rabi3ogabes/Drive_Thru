<?php

declare(strict_types=1);

namespace App\Domain\Staff;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * Stops anyone handing out more than they hold. Owners and platform admins
 * may do anything; everyone else can only grant permissions they have
 * themselves, only in branches they cover, and only to people who are not
 * more powerful than they are.
 */
final class StaffGuard
{
    /** True when the actor has unrestricted control of the restaurant's team. */
    public function isUnrestricted(User $actor, Restaurant $restaurant): bool
    {
        if ($actor->isSuperAdmin()) {
            return $actor->is_active;
        }

        $employment = $actor->employmentAt($restaurant);

        return $employment !== null && $employment->isActive() && $employment->is_owner;
    }

    public function canManageStaff(User $actor, Restaurant $restaurant): bool
    {
        return $actor->hasRestaurantPermission($restaurant, RestaurantPermission::ManageStaff);
    }

    /**
     * @param  iterable<RestaurantPermission>  $permissions
     */
    public function canGrant(User $actor, Restaurant $restaurant, iterable $permissions): bool
    {
        if ($this->isUnrestricted($actor, $restaurant)) {
            return true;
        }

        $held = $this->heldPermissions($actor, $restaurant);

        foreach ($permissions as $permission) {
            if (! $held->contains($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $branchIds
     */
    public function canAssignBranches(User $actor, Restaurant $restaurant, array $branchIds, bool $allBranches): bool
    {
        if ($this->isUnrestricted($actor, $restaurant)) {
            return true;
        }

        $mine = $actor->employmentAt($restaurant)?->branchIds();

        if ($mine === null) {
            return true;
        }

        return ! $allBranches && $branchIds !== [] && array_diff($branchIds, $mine) === [];
    }

    /** Whether the actor may edit, suspend or remove this team member. */
    public function canManage(User $actor, Employee $target): bool
    {
        $restaurant = $target->restaurant;

        if ($restaurant === null || ! $this->canManageStaff($actor, $restaurant)) {
            return false;
        }

        // The owner is managed by the platform only.
        if ($target->is_owner) {
            return $actor->isSuperAdmin();
        }

        if ($this->isUnrestricted($actor, $restaurant)) {
            return true;
        }

        if ($target->user_id === $actor->id) {
            return false;
        }

        $targetPermissions = $target->position !== null ? $target->position->permissions : collect();

        if (! $this->canGrant($actor, $restaurant, $targetPermissions)) {
            return false;
        }

        $targetBranches = $target->branchIds();

        return $this->canAssignBranches($actor, $restaurant, $targetBranches ?? [], $targetBranches === null);
    }

    /**
     * @param  iterable<RestaurantPermission>  $permissions
     *
     * @throws AuthorizationException
     */
    public function ensureCanGrant(User $actor, Restaurant $restaurant, iterable $permissions): void
    {
        if (! $this->canGrant($actor, $restaurant, $permissions)) {
            throw new AuthorizationException(__('staff.errors.escalation'));
        }
    }

    /**
     * @param  list<int>  $branchIds
     *
     * @throws AuthorizationException
     */
    public function ensureCanAssignBranches(User $actor, Restaurant $restaurant, array $branchIds, bool $allBranches): void
    {
        if (! $this->canAssignBranches($actor, $restaurant, $branchIds, $allBranches)) {
            throw new AuthorizationException(__('staff.errors.branches'));
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function ensureCanManage(User $actor, Employee $target): void
    {
        if (! $this->canManage($actor, $target)) {
            throw new AuthorizationException(__('staff.errors.manage'));
        }
    }

    /**
     * @return Collection<int, RestaurantPermission>
     */
    private function heldPermissions(User $actor, Restaurant $restaurant): Collection
    {
        $employment = $actor->employmentAt($restaurant);

        if ($employment === null || ! $employment->isActive() || $employment->position === null) {
            return collect();
        }

        return $employment->position->permissions;
    }
}
