<?php

declare(strict_types=1);

namespace App\Domain\Loyalty;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Tier;
use Illuminate\Support\Collection;

/**
 * Tiers from the points a guest earned by ordering in the program's window
 * (earned + bonus − reversed; points given back or adjusted don't count).
 * Guests move up as they earn and down when old points leave the window.
 */
final class Tiers
{
    public function qualifyingPoints(Customer $customer): int
    {
        $months = (int) (LoyaltyProgram::withoutTenancy()->where('restaurant_id', $customer->restaurant_id)->value('tier_window_months') ?? 12);
        $types = array_map(fn (LedgerType $t): string => $t->value, array_filter(LedgerType::cases(), fn (LedgerType $t): bool => $t->qualifies()));

        return max(0, (int) LedgerEntry::withoutTenancy()
            ->where('customer_id', $customer->id)
            ->whereIn('type', $types)
            ->where('created_at', '>=', now()->subMonths($months))
            ->sum('points'));
    }

    public function recompute(Customer $customer): ?Tier
    {
        $points = $this->qualifyingPoints($customer);
        $tier = $this->tiers($customer->restaurant_id)->filter(fn (Tier $t): bool => $t->threshold_points <= $points)->last();

        if ($customer->tier_id !== $tier?->id) {
            Customer::withoutTenancy()->whereKey($customer->id)->update(['tier_id' => $tier?->id]);
            $customer->setAttribute('tier_id', $tier?->id);
            $customer->syncOriginalAttribute('tier_id');
            $customer->setRelation('tier', $tier);
        }

        return $tier;
    }

    /**
     * @return array{tier: ?Tier, next: ?Tier, points: int, needed: int, percent: int}
     */
    public function progress(Customer $customer): array
    {
        $points = $this->qualifyingPoints($customer);
        $tiers = $this->tiers($customer->restaurant_id);
        $tier = $tiers->filter(fn (Tier $t): bool => $t->threshold_points <= $points)->last();
        $next = $tiers->first(fn (Tier $t): bool => $t->threshold_points > $points);
        $from = $tier->threshold_points ?? 0;

        return [
            'tier' => $tier,
            'next' => $next,
            'points' => $points,
            'needed' => $next === null ? 0 : $next->threshold_points - $points,
            'percent' => $next === null ? 100 : (int) floor(($points - $from) / max(1, $next->threshold_points - $from) * 100),
        ];
    }

    /** Bronze, Silver, Gold, Platinum: the owner sets the thresholds and multipliers. */
    public function standardPreset(LoyaltyProgram $program): void
    {
        $names = [
            ['en' => 'Bronze', 'ar' => 'برونزي'],
            ['en' => 'Silver', 'ar' => 'فضي'],
            ['en' => 'Gold', 'ar' => 'ذهبي'],
            ['en' => 'Platinum', 'ar' => 'بلاتيني'],
        ];

        foreach ($names as $sort => $name) {
            Tier::withoutTenancy()->create(['restaurant_id' => $program->restaurant_id, 'name' => $name, 'threshold_points' => 0, 'sort' => $sort]);
        }
    }

    /**
     * @return Collection<int, Tier>
     */
    public function tiers(int $restaurantId): Collection
    {
        return Tier::withoutTenancy()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('threshold_points')
            ->orderBy('sort')
            ->get();
    }
}
