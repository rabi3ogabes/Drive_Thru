<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Operations;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Enums\StationKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Integrations\Models\ApiClient;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;

/**
 * Who is working an order: a signed-in staff member (their position's
 * permissions and branches) or a paired branch screen (what its kind is for,
 * on its own branch only). Every desk action asks the operator first.
 */
final readonly class Operator
{
    /**
     * @param  list<RestaurantPermission>  $permissions
     * @param  ?list<int>  $branchIds  null = every branch of the restaurant
     */
    private function __construct(
        public string $actorType,
        public int $actorId,
        public int $restaurantId,
        public ?array $branchIds,
        public array $permissions,
        public bool $canOverride,
        public ?StationKind $station = null,
    ) {}

    public static function staff(User $user, int $restaurantId): self
    {
        $employment = $user->employmentAt($restaurantId);
        $permissions = array_values(array_filter(
            RestaurantPermission::cases(),
            fn (RestaurantPermission $p): bool => $user->is_active && $user->hasRestaurantPermission($restaurantId, $p),
        ));

        $branchIds = match (true) {
            $user->isSuperAdmin(), $employment === null, $employment->is_owner, $employment->all_branches => null,
            default => array_values(array_map('intval', $employment->branches()->pluck('branches.id')->all())),
        };

        $toggle = (bool) Restaurant::query()->whereKey($restaurantId)->value('order_override_enabled');

        return new self(
            TransitionOrder::STAFF,
            $user->id,
            $restaurantId,
            $branchIds,
            $permissions,
            $toggle && in_array(RestaurantPermission::OverrideOrders, $permissions, true),
        );
    }

    /**
     * A person who stepped up on a paired screen with their PIN: their own
     * permissions, limited to the screen's branch, and the action is theirs.
     */
    public static function staffOnDevice(User $user, Device $device): self
    {
        $staff = self::staff($user, $device->restaurant_id);

        return new self(
            TransitionOrder::STAFF,
            $user->id,
            $device->restaurant_id,
            $staff->covers($device->branch_id) ? [$device->branch_id] : [],
            $staff->permissions,
            $staff->canOverride,
        );
    }

    /**
     * Another system through the public API: it may move orders along and
     * cancel them at every branch, never override statuses or refund.
     */
    public static function integration(ApiClient $client): self
    {
        return new self(
            TransitionOrder::INTEGRATION,
            $client->id,
            $client->restaurant_id,
            null,
            [RestaurantPermission::ViewOrders, RestaurantPermission::OperateOrders, RestaurantPermission::OperateKitchen, RestaurantPermission::OperatePickup],
            false,
        );
    }

    public static function device(Device $device): self
    {
        $permissions = match ($device->kind) {
            DeviceKind::Kitchen => [RestaurantPermission::ViewOrders, RestaurantPermission::OperateKitchen],
            DeviceKind::Cashier => [RestaurantPermission::ViewOrders, RestaurantPermission::OperateOrders, RestaurantPermission::OperatePickup, RestaurantPermission::CollectPayments],
            DeviceKind::Pickup => [RestaurantPermission::ViewOrders, RestaurantPermission::OperatePickup, RestaurantPermission::CollectPayments],
            DeviceKind::Tv => [RestaurantPermission::ViewOrders],
            DeviceKind::Pager => [RestaurantPermission::OperatePager],
            DeviceKind::ReadyBoard => [],
        };

        return new self(
            TransitionOrder::DEVICE,
            $device->id,
            $device->restaurant_id,
            [$device->branch_id],
            $permissions,
            false,
            $device->kind === DeviceKind::Kitchen ? $device->station?->kind : null,
        );
    }

    public function can(RestaurantPermission ...$any): bool
    {
        foreach ($any as $permission) {
            if (in_array($permission, $this->permissions, true)) {
                return true;
            }
        }

        return false;
    }

    public function covers(Branch|int $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $restaurantId = $branch instanceof Branch ? $branch->restaurant_id : Branch::withoutTenancy()->whereKey($branchId)->value('restaurant_id');

        return $restaurantId === $this->restaurantId && ($this->branchIds === null || in_array($branchId, $this->branchIds, true));
    }

    /** The user id for the audit trail (screens act anonymously as the device). */
    public function userId(): ?int
    {
        return $this->actorType === TransitionOrder::STAFF ? $this->actorId : null;
    }
}
