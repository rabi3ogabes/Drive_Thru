<?php

declare(strict_types=1);

namespace App\Filament\App;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * The restaurant and person behind the current workspace request, plus the
 * branch scope that keeps branch-level staff inside their own branches.
 */
final class Workspace
{
    public static function restaurant(): Restaurant
    {
        $restaurant = Filament::getTenant();

        if (! $restaurant instanceof Restaurant) {
            throw new RuntimeException('No restaurant workspace is active.');
        }

        return $restaurant;
    }

    public static function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new RuntimeException('Nobody is signed in.');
        }

        return $user;
    }

    public static function can(RestaurantPermission $permission): bool
    {
        $user = auth()->user();
        $restaurant = Filament::getTenant();

        return $user instanceof User && $restaurant instanceof Restaurant && $user->hasRestaurantPermission($restaurant, $permission);
    }

    /**
     * Branch ids the current person works in, or null for all branches.
     *
     * @return ?list<int>
     */
    public static function branchIds(): ?array
    {
        $user = self::user();

        if ($user->isSuperAdmin()) {
            return null;
        }

        $employment = $user->employmentAt(self::restaurant());

        // Null from branchIds() means "every branch"; no membership means none.
        return $employment === null ? [] : $employment->branchIds();
    }

    /**
     * Limits a branch query to the branches the current person works in.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToBranches(Builder $query, string $column = 'id'): Builder
    {
        $ids = self::branchIds();

        return $ids === null ? $query : $query->whereIn($query->qualifyColumn($column), $ids);
    }

    /**
     * Branch options (id => name) the current person may pick from.
     *
     * @return array<int, string>
     */
    public static function branchOptions(): array
    {
        return self::scopeToBranches(Branch::query())
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Branch $branch): array => [$branch->id => $branch->name])
            ->all();
    }
}
