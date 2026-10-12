<?php

declare(strict_types=1);

namespace App\Domain\Loyalty;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyProgram;

/**
 * How many of the points a guest asked to use can actually be used: no more
 * than they have, no more than the program's share of what is left to pay,
 * in whole redeem steps, and only from the program's minimum up.
 */
final class PointsRedemption
{
    /**
     * @return array{points: int, discountMinor: int}
     */
    public function clamp(LoyaltyProgram $program, Customer $customer, int $requested, int $remainingMinor): array
    {
        $none = ['points' => 0, 'discountMinor' => 0];

        if (! $program->isActive() || $requested <= 0 || $remainingMinor <= 0) {
            return $none;
        }

        $step = (int) $program->redeem_points;
        $stepValue = (int) $program->redeem_value_minor;
        $maxValue = intdiv($remainingMinor * (int) $program->max_redeem_percent, 100);
        $maxPoints = intdiv($maxValue, $stepValue) * $step;

        $points = min($requested, $customer->points_balance, $maxPoints);
        $points = intdiv($points, $step) * $step;

        if ($points < (int) $program->min_redeem_points) {
            return $none;
        }

        return ['points' => $points, 'discountMinor' => $program->valueOf($points)];
    }
}
