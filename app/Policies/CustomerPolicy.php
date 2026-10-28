<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Guests: "View guests" in the restaurant being worked in. Guests create
 * themselves by ordering, so nobody adds, edits or deletes them here; the
 * profile actions (points, rewards, consent) check their own permission.
 */
final class CustomerPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ViewCustomers;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
