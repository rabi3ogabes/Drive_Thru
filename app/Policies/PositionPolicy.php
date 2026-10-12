<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Access\Models\Position;
use App\Domain\Staff\StaffGuard;
use App\Models\User;
use App\Policies\Concerns\ChecksRestaurantPermission;

/**
 * Positions are managed with the team. A position can only be edited by
 * someone who holds every permission in it; built-in ones can't be deleted.
 */
final class PositionPolicy
{
    use ChecksRestaurantPermission;

    public function __construct(private readonly StaffGuard $guard) {}

    public function viewAny(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageStaff]);
    }

    public function view(User $user, Position $position): bool
    {
        return $this->inWorkspace($position->restaurant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Position $position): bool
    {
        $restaurant = $this->workspace();

        return $restaurant !== null
            && $this->inWorkspace($position->restaurant_id)
            && $this->viewAny($user)
            && $this->guard->canGrant($user, $restaurant, $position->permissions);
    }

    public function delete(User $user, Position $position): bool
    {
        return ! $position->is_system && $this->update($user, $position);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
