<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A promotion (or coupon) used on an order. Released when the order is
 * cancelled, so the guest's use comes back.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $promotion_id
 * @property ?int $coupon_id
 * @property int $customer_id
 * @property int $order_id
 * @property int $discount_minor
 * @property ?CarbonImmutable $released_at
 */
#[Fillable(['restaurant_id', 'promotion_id', 'coupon_id', 'customer_id', 'order_id', 'discount_minor', 'released_at'])]
class Redemption extends Model
{
    use BelongsToRestaurant;

    protected $table = 'promotion_redemptions';

    /** @var array<string, mixed> */
    protected $attributes = [
        'coupon_id' => null,
        'discount_minor' => 0,
        'released_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'released_at' => 'immutable_datetime',
        ];
    }
}
