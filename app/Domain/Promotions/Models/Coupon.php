<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A code that unlocks a promotion, with its own limits. Codes are stored
 * upper-case and unique within a restaurant.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $promotion_id
 * @property string $code
 * @property ?int $usage_limit
 * @property ?int $per_customer_limit
 * @property int $used_count
 * @property ?CarbonImmutable $expires_at
 * @property-read Promotion $promotion
 */
#[Fillable(['restaurant_id', 'promotion_id', 'code', 'usage_limit', 'per_customer_limit', 'used_count', 'expires_at'])]
class Coupon extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'usage_limit' => null,
        'per_customer_limit' => null,
        'used_count' => 0,
        'expires_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public static function normalize(string $code): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/', '', $code));
    }

    public static function findCode(int $restaurantId, string $code): ?self
    {
        return self::withoutTenancy()->with('promotion')->where('restaurant_id', $restaurantId)->where('code', self::normalize($code))->first();
    }
}
