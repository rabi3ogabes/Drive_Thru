<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Models\User;
use App\Policies\Concerns\ChecksRestaurantPermission;
use Illuminate\Database\Eloquent\Model;

/**
 * The menu catalog (items, sections, option groups, schedules) is edited by
 * people with menu.manage, inside their own restaurant only. Branch-level
 * availability and stock are handled on the Branch menu page.
 */
final class MenuPolicy
{
    use ChecksRestaurantPermission;

    public function viewAny(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageMenu]);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function reorder(User $user): bool
    {
        return $this->viewAny($user);
    }

    private function owns(User $user, Model $record): bool
    {
        return $this->inWorkspace((int) $record->getAttribute('restaurant_id')) && $this->viewAny($user);
    }
}
