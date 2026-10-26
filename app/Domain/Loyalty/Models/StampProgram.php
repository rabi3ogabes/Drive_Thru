<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A stamp card: each item of a product (or of a category) adds a stamp;
 * a full card puts the reward in the guest's wallet and starts again.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property string $target_type product | category
 * @property int $target_id
 * @property int $stamps_needed
 * @property int $reward_id
 * @property ?int $card_expiry_days
 * @property int $daily_cap stamps one guest can get per day on this card
 * @property bool $is_active
 * @property ?CarbonImmutable $archived_at
 * @property int $sort
 * @property-read Reward $reward
 */
#[Fillable(['restaurant_id', 'name', 'target_type', 'target_id', 'stamps_needed', 'reward_id', 'card_expiry_days', 'daily_cap', 'is_active', 'archived_at', 'sort'])]
class StampProgram extends Model
{
    use BelongsToRestaurant, HasTranslations;

    public const TARGETS = ['product', 'category'];

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'card_expiry_days' => null,
        'daily_cap' => 3,
        'is_active' => true,
        'archived_at' => null,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'stamps_needed' => 'integer',
            'reward_id' => 'integer',
            'card_expiry_days' => 'integer',
            'daily_cap' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'immutable_datetime',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Reward, $this>
     */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class)->withoutTenancy();
    }

    /**
     * @return HasMany<StampCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(StampCard::class);
    }

    /** Running for guests right now. */
    public function isLive(): bool
    {
        return $this->is_active && $this->archived_at === null;
    }

    /** The product or category name, for "Buy 5 Ice creams". */
    public function targetName(): string
    {
        $model = $this->target_type === 'category'
            ? MenuCategory::withoutTenancy()->find($this->target_id)
            : Product::withoutTenancy()->find($this->target_id);

        return $model === null ? '' : (string) $model->name;
    }
}
