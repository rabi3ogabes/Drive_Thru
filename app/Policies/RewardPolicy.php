<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;

/** Rewards in the catalog: "ManageLoyalty" in the restaurant being worked in. */
final class RewardPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ManageLoyalty;
    }
}
