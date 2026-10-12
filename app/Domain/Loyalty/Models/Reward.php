<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * An item in the restaurant's rewards catalog: claimed with points (or
 * given) into the guest's wallet, then used at checkout.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property ?string $description
 * @property RewardType $type
 * @property ?int $points_cost null = can't be claimed, only given
 * @property ?int $product_id free item
 * @property ?int $value basis points (percent), minor units (fixed) or points (bonus)
 * @property int $min_order_minor
 * @property int $valid_days
 * @property ?int $stock null = unlimited
 * @property bool $is_active
 * @property ?CarbonImmutable $archived_at
 * @property-read ?Product $product
 */
#[Fillable(['restaurant_id', 'name', 'description', 'type', 'points_cost', 'product_id', 'value', 'min_order_minor', 'valid_days', 'stock', 'is_active', 'archived_at'])]
class Reward extends Model
{
    use BelongsToRestaurant, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'description' => null,
        'points_cost' => null,
        'product_id' => null,
        'value' => null,
        'min_order_minor' => 0,
        'valid_days' => 30,
        'stock' => null,
        'is_active' => true,
        'archived_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'type' => RewardType::class,
            'points_cost' => 'integer',
            'value' => 'integer',
            'min_order_minor' => 'integer',
            'valid_days' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<WalletReward, $this>
     */
    public function wallet(): HasMany
    {
        return $this->hasMany(WalletReward::class);
    }

    public function isClaimable(): bool
    {
        return $this->is_active && $this->archived_at === null && $this->type !== RewardType::BonusPoints
            && ($this->points_cost ?? 0) > 0 && ($this->stock === null || $this->stock > 0);
    }
}
