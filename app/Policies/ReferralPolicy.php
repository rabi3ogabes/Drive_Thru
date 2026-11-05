<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Referrals: "Manage campaigns" in the restaurant being worked in. They are
 * a record of what happened, so nobody creates, edits or deletes them.
 */
final class ReferralPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ManageCampaigns;
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
