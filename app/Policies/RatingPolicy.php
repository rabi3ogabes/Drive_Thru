<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Guest ratings: "View reports" in the restaurant being worked in. They are
 * the guests' own words, so nobody creates, edits or deletes them.
 */
final class RatingPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ViewReports;
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
