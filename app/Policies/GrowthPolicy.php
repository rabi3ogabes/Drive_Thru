<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Models\User;
use App\Policies\Concerns\ChecksRestaurantPermission;
use Illuminate\Database\Eloquent\Model;

/**
 * Loyalty and offers records: one permission per family (rewards need
 * "Manage loyalty", promotions and coupons "Manage promotions"), always
 * inside the restaurant being worked in.
 */
abstract class GrowthPolicy
{
    use ChecksRestaurantPermission;

    abstract protected function permission(): RestaurantPermission;

    public function viewAny(User $user): bool
    {
        return $this->canInWorkspace($user, [$this->permission()]);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->inWorkspace((int) $record->getAttribute('restaurant_id')) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->view($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
