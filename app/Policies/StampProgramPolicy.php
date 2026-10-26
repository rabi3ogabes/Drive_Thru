<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;

/** Stamp cards: "ManageLoyalty" in the restaurant being worked in. */
final class StampProgramPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ManageLoyalty;
    }
}
