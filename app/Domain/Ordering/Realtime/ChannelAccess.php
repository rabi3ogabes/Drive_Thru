<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Realtime;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Models\Branch;
use App\Models\User;

/** Who on the staff side may listen to which live channel (used by routes/channels.php). */
final class ChannelAccess
{
    private const BRANCH_PERMISSIONS = [
        RestaurantPermission::ViewOrders,
        RestaurantPermission::OperateOrders,
        RestaurantPermission::OperateKitchen,
        RestaurantPermission::OperatePickup,
    ];

    /** A branch's live orders: anyone who works its orders, kitchen or window, and covers the branch. */
    public function branchOrders(User $user, int $branchId): bool
    {
        $branch = Branch::withoutTenancy()->find($branchId);

        if ($branch === null || ! $user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! ($user->employmentAt($branch->restaurant_id)?->coversBranch($branch) ?? false)) {
            return false;
        }

        foreach (self::BRANCH_PERMISSIONS as $permission) {
            if ($user->hasRestaurantPermission($branch->restaurant_id, $permission)) {
                return true;
            }
        }

        return false;
    }

    /** Platform staff who look after clients: live activity across every restaurant. */
    public function platformDashboard(User $user): bool
    {
        return $user->is_active && ($user->isSuperAdmin() || $user->hasPlatformPermission(PlatformPermission::ViewClients));
    }

    /** Every branch at once (sales included): people with reports who cover every branch. */
    public function restaurantDashboard(User $user, int $restaurantId): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $employment = $user->employmentAt($restaurantId);
        $everyBranch = $employment !== null && ($employment->is_owner || $employment->all_branches);

        return $everyBranch && $user->hasRestaurantPermission($restaurantId, RestaurantPermission::ViewReports);
    }
}
