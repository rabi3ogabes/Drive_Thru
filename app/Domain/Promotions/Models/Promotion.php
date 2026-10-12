<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Models;

use App\Domain\Menu\Models\Product;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A promotion: what it gives (type + values), to what (scope), when (dates,
 * days, hours in restaurant time), to whom (first order, birthday), how often
 * (limits) and whether it combines with others. Coupon-only promotions apply
 * only through one of their codes. Archived, never deleted once used.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property PromotionType $type
 * @property ?int $value_bp
 * @property ?int $value_minor fixed amount off, or the points for BonusPoints
 * @property ?int $buy_qty
 * @property ?int $get_qty
 * @property ?int $free_product_id
 * @property ?list<int> $combo_product_ids
 * @property ?int $combo_price_minor
 * @property string $scope all | categories | products
 * @property ?list<int> $scope_ids
 * @property ?list<int> $branch_ids
 * @property bool $first_order
 * @property bool $birthday
 * @property ?list<int> $days 0 = Sunday
 * @property ?string $time_from
 * @property ?string $time_to
 * @property ?CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 * @property int $min_subtotal_minor
 * @property bool $requires_coupon
 * @property bool $stackable
 * @property int $priority
 * @property ?int $usage_limit
 * @property ?int $per_customer_limit
 * @property int $used_count
 * @property ?CarbonImmutable $archived_at
 * @property-read ?Product $freeProduct
 */
#[Fillable([
    'restaurant_id', 'name', 'type', 'value_bp', 'value_minor', 'buy_qty', 'get_qty', 'free_product_id', 'combo_product_ids',
    'combo_price_minor', 'scope', 'scope_ids', 'branch_ids', 'first_order', 'birthday', 'days', 'time_from', 'time_to',
    'starts_at', 'ends_at', 'min_subtotal_minor', 'requires_coupon', 'stackable', 'priority', 'usage_limit', 'per_customer_limit',
    'used_count', 'archived_at',
])]
class Promotion extends Model
{
    use BelongsToRestaurant, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'value_bp' => null,
        'value_minor' => null,
        'buy_qty' => null,
        'get_qty' => null,
        'free_product_id' => null,
        'combo_product_ids' => null,
        'combo_price_minor' => null,
        'scope' => 'all',
        'scope_ids' => null,
        'branch_ids' => null,
        'first_order' => false,
        'birthday' => false,
        'days' => null,
        'time_from' => null,
        'time_to' => null,
        'starts_at' => null,
        'ends_at' => null,
        'min_subtotal_minor' => 0,
        'requires_coupon' => false,
        'stackable' => false,
        'priority' => 0,
        'usage_limit' => null,
        'per_customer_limit' => null,
        'used_count' => 0,
        'archived_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value_bp' => 'integer',
            'value_minor' => 'integer',
            'buy_qty' => 'integer',
            'get_qty' => 'integer',
            'combo_product_ids' => 'array',
            'combo_price_minor' => 'integer',
            'scope_ids' => 'array',
            'branch_ids' => 'array',
            'first_order' => 'boolean',
            'birthday' => 'boolean',
            'days' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'min_subtotal_minor' => 'integer',
            'requires_coupon' => 'boolean',
            'stackable' => 'boolean',
            'priority' => 'integer',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'used_count' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * @return HasMany<Redemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function freeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }
}
