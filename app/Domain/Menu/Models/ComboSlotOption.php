<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Menu\Concerns\BustsMenuCache;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product a guest can pick for a combo slot, with an optional upcharge
 * (e.g. +3 QAR for onion rings instead of fries).
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $combo_slot_id
 * @property int $product_id
 * @property int $price_delta_minor
 * @property bool $is_default
 * @property int $sort
 * @property-read Product $product
 */
#[Fillable(['restaurant_id', 'combo_slot_id', 'product_id', 'price_delta_minor', 'is_default', 'sort'])]
class ComboSlotOption extends Model
{
    use BelongsToRestaurant, BustsMenuCache;

    /** @var array<string, mixed> */
    protected $attributes = [
        'price_delta_minor' => 0,
        'is_default' => false,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_delta_minor' => 'integer',
            'is_default' => 'boolean',
            'sort' => 'integer',
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
     * @return BelongsTo<ComboSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(ComboSlot::class, 'combo_slot_id');
    }
}
