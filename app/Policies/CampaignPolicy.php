<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Campaigns\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Campaigns: "Manage campaigns" in the restaurant being worked in. Only a
 * draft or scheduled campaign can be edited; nothing is ever deleted, so
 * the results stay.
 */
final class CampaignPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ManageCampaigns;
    }

    public function update(User $user, Model $record): bool
    {
        return $record instanceof Campaign && $record->status->isEditable() && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
