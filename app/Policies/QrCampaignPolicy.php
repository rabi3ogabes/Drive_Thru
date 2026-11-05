<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracked QR codes: "Manage campaigns" in the restaurant being worked in.
 * They are switched off rather than deleted, so their results stay.
 */
final class QrCampaignPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ManageCampaigns;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
