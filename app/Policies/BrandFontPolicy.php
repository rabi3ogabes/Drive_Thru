<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;

/**
 * Brand fonts: "Manage menu" (the theme lives on Menu design), always in the
 * restaurant being worked in.
 */
final class BrandFontPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::ManageMenu;
    }
}
