<?php

declare(strict_types=1);

namespace App\Domain\Loyalty;

use App\Domain\Loyalty\Models\EarnRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Which earn boost applies to an item: the strongest live one matching its product, category or branch. */
final class EarnRules
{
    /**
     * @return Collection<int, EarnRule>
     */
    public function forRestaurant(int $restaurantId): Collection
    {
        return EarnRule::withoutTenancy()->where('restaurant_id', $restaurantId)->get();
    }

    /**
     * @param  Collection<int, EarnRule>  $rules
     * @return int basis points, 10000 when nothing matches
     */
    public function multiplier(Collection $rules, ?int $productId, ?int $categoryId, int $branchId, CarbonInterface $at): int
    {
        $ids = ['product' => $productId, 'category' => $categoryId, 'branch' => $branchId];

        $best = $rules
            ->filter(fn (EarnRule $rule): bool => ($ids[$rule->scope] ?? null) === $rule->scope_id && $rule->isLiveAt($at))
            ->max('multiplier_bp');

        return max(10_000, (int) $best);
    }
}
