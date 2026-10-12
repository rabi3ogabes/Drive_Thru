<?php

declare(strict_types=1);

namespace App\Domain\Branches\Actions;

use App\Domain\Billing\Exceptions\PlanLimitReached;
use App\Domain\Billing\PlanLimits;
use App\Domain\Branches\Enums\LaneKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Lane;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a branch within the plan's limit, with a unique code and a first
 * drive-thru lane so it can take orders straight away.
 */
final class CreateBranch
{
    public const RESERVED_CODES = ['orders', 'account', 'rewards', 'sign-in', 'checkout', 'manifest-webmanifest', 'offline', 'go', 'overview'];

    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws PlanLimitReached
     */
    public function handle(Restaurant $restaurant, array $attributes): Branch
    {
        return DB::transaction(function () use ($restaurant, $attributes): Branch {
            // Lock the restaurant row so two parallel creates can't both squeeze under the limit.
            Restaurant::query()->whereKey($restaurant->id)->lockForUpdate()->first();

            $this->limits->ensure($restaurant, PlanLimits::BRANCHES);

            $name = $attributes['name'] ?? [];
            $code = $attributes['code'] ?? null;

            $branch = Branch::withoutTenancy()->create(array_merge($attributes, [
                'restaurant_id' => $restaurant->id,
                'code' => self::uniqueCode($restaurant, filled($code) ? (string) $code : (string) (is_array($name) ? ($name['en'] ?? reset($name)) : $name)),
                'sort' => (int) Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->max('sort') + 1,
            ]));

            Lane::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'name' => ['ar' => __('branches.defaults.lane', ['number' => 1], 'ar'), 'en' => __('branches.defaults.lane', ['number' => 1], 'en')],
                'kind' => LaneKind::Lane,
            ]);

            return $branch;
        });
    }

    public static function uniqueCode(Restaurant $restaurant, string $wanted, ?int $ignoreId = null): string
    {
        $base = Str::slug($wanted) ?: 'branch';

        // These paths live under a restaurant's link, so no branch may take them.
        if (in_array($base, self::RESERVED_CODES, true)) {
            $base .= '-branch';
        }
        $code = $base;
        $suffix = 2;

        while (Branch::withoutTenancy()->withTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $code = "{$base}-{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
