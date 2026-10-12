<?php

declare(strict_types=1);

namespace App\Domain\Staff\Actions;

use App\Domain\Access\DefaultPositions;
use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\Position;
use App\Domain\Staff\StaffGuard;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Creates, edits, resets and deletes positions. Nobody can build a position
 * with permissions they don't hold themselves, built-in positions can be
 * tuned or reset but never deleted, and a position in use can't be deleted.
 */
final class SavePosition
{
    public function __construct(private readonly StaffGuard $guard) {}

    /**
     * @param  array{ar?: ?string, en?: ?string}  $name
     * @param  array{ar?: ?string, en?: ?string}  $description
     * @param  list<RestaurantPermission|string>  $permissions
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(Restaurant $restaurant, User $actor, array $name, array $description, array $permissions, ?Position $position = null): Position
    {
        if (! $this->guard->canManageStaff($actor, $restaurant)) {
            throw new AuthorizationException(__('staff.errors.manage'));
        }

        $permissions = collect($permissions)
            ->map(fn (RestaurantPermission|string $permission): RestaurantPermission => $permission instanceof RestaurantPermission ? $permission : RestaurantPermission::from($permission))
            ->unique()
            ->values();

        if ($permissions->isEmpty()) {
            throw ValidationException::withMessages(['permissions' => __('positions.errors.empty')]);
        }

        // Editing a position also changes what its current holders can do,
        // so the actor must hold both the old and the new permissions.
        $this->guard->ensureCanGrant($actor, $restaurant, $permissions);

        if ($position !== null) {
            $this->guard->ensureCanGrant($actor, $restaurant, $position->permissions);
        }

        $position ??= new Position(['restaurant_id' => $restaurant->id, 'is_system' => false]);

        $position->fill([
            'name' => array_filter($name, 'filled'),
            'description' => array_filter($description, 'filled') ?: null,
            'permissions' => $permissions->all(),
        ]);

        if (! $position->exists) {
            $position->sort = (int) Position::withoutTenancy()->where('restaurant_id', $restaurant->id)->max('sort') + 1;
        }

        $position->save();

        return $position;
    }

    /**
     * Puts a built-in position back to its original permissions.
     *
     * @throws AuthorizationException
     */
    public function reset(Position $position, User $actor): Position
    {
        $defaults = DefaultPositions::all()[$position->key ?? ''] ?? null;

        if (! $position->is_system || $defaults === null) {
            throw new AuthorizationException(__('positions.errors.not_system'));
        }

        $this->guard->ensureCanGrant($actor, $position->restaurant, $defaults);
        $this->guard->ensureCanGrant($actor, $position->restaurant, $position->permissions);

        $position->forceFill(['permissions' => $defaults])->save();

        return $position;
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function delete(Position $position, User $actor): void
    {
        if ($position->is_system) {
            throw new AuthorizationException(__('positions.errors.system_delete'));
        }

        $this->guard->ensureCanGrant($actor, $position->restaurant, $position->permissions);

        if ($position->employees()->exists()) {
            throw ValidationException::withMessages(['position' => __('positions.errors.in_use')]);
        }

        $position->delete();
    }
}
