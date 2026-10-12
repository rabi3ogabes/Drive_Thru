<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A restaurant's points program. There are no default values: it does
 * nothing until the owner has set every one and switched it on.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property bool $is_enabled
 * @property ?int $earn_points_per_unit points per 1 major currency unit spent
 * @property ?int $redeem_points
 * @property ?int $redeem_value_minor what $redeem_points are worth
 * @property ?int $max_redeem_percent of the order that points may pay
 * @property ?int $min_redeem_points
 * @property ?int $expiry_months null = never
 * @property int $tier_window_months
 */
#[Fillable(['restaurant_id', 'is_enabled', 'earn_points_per_unit', 'redeem_points', 'redeem_value_minor', 'max_redeem_percent', 'min_redeem_points', 'expiry_months', 'tier_window_months'])]
class LoyaltyProgram extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_enabled' => false,
        'earn_points_per_unit' => null,
        'redeem_points' => null,
        'redeem_value_minor' => null,
        'max_redeem_percent' => null,
        'min_redeem_points' => null,
        'expiry_months' => null,
        'tier_window_months' => 12,
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'earn_points_per_unit' => 'integer',
            'redeem_points' => 'integer',
            'redeem_value_minor' => 'integer',
            'max_redeem_percent' => 'integer',
            'min_redeem_points' => 'integer',
            'expiry_months' => 'integer',
            'tier_window_months' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->is_enabled
            && ($this->earn_points_per_unit ?? 0) > 0
            && ($this->redeem_points ?? 0) > 0
            && ($this->redeem_value_minor ?? 0) > 0
            && ($this->max_redeem_percent ?? 0) > 0 && $this->max_redeem_percent <= 100
            && ($this->min_redeem_points ?? 0) > 0;
    }

    /** Points earned for a net amount: whole points only, rounded down. */
    public function pointsFor(int $netMinor, string $currency, int $multiplierBp = 10_000): int
    {
        if (! $this->isActive() || $netMinor <= 0) {
            return 0;
        }

        $units = $netMinor / (10 ** Money::decimals($currency));

        return (int) floor(round($units * (int) $this->earn_points_per_unit * $multiplierBp / 10_000, 6));
    }

    /** What a number of points is worth, in minor units. */
    public function valueOf(int $points): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        return intdiv($points, (int) $this->redeem_points) * (int) $this->redeem_value_minor;
    }

    /** Points needed to cover an amount, in whole redeem steps (rounded up). */
    public function pointsForValue(int $minor): int
    {
        if (! $this->isActive() || $minor <= 0) {
            return 0;
        }

        return (int) ceil($minor / (int) $this->redeem_value_minor) * (int) $this->redeem_points;
    }
}
