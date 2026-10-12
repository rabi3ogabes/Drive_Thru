<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Billing\Exceptions\PlanLimitReached;
use App\Domain\Branches\Models\Branch;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * Counts what a restaurant uses against its plan's limits. A restaurant with
 * no plan, or a plan without a value, has no limit.
 */
final class PlanLimits
{
    public const BRANCHES = 'branches';

    public const EMPLOYEES = 'employees';

    public function limit(Restaurant $restaurant, string $key): ?int
    {
        return $restaurant->plan()?->limit($key);
    }

    public function used(Restaurant $restaurant, string $key): int
    {
        return match ($key) {
            self::BRANCHES => Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->count(),
            self::EMPLOYEES => Employee::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('is_owner', false)->count(),
            default => 0,
        };
    }

    /** How many more can be added; null means unlimited. */
    public function remaining(Restaurant $restaurant, string $key): ?int
    {
        $limit = $this->limit($restaurant, $key);

        return $limit === null ? null : max(0, $limit - $this->used($restaurant, $key));
    }

    public function allows(Restaurant $restaurant, string $key): bool
    {
        $remaining = $this->remaining($restaurant, $key);

        return $remaining === null || $remaining > 0;
    }

    /**
     * @throws PlanLimitReached
     */
    public function ensure(Restaurant $restaurant, string $key): void
    {
        if (! $this->allows($restaurant, $key)) {
            throw new PlanLimitReached($key, (int) $this->limit($restaurant, $key), $restaurant->plan()?->name);
        }
    }
}
