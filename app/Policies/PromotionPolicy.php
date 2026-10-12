<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;

/** Promotions and their coupon codes: "ManagePromotions" in the restaurant being worked in. */
final class PromotionPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ManagePromotions;
    }
}
