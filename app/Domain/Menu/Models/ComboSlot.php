<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Menu\Concerns\BustsMenuCache;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * One part of a combo ("Main", "Side", "Drink") with the products a guest
 * may pick for it.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $combo_product_id
 * @property string $name
 * @property int $sort
 * @property-read Collection<int, ComboSlotOption> $options
 */
#[Fillable(['restaurant_id', 'combo_product_id', 'name', 'sort'])]
class ComboSlot extends Model
{
    use BelongsToRestaurant, BustsMenuCache, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = ['sort' => 0];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'combo_product_id');
    }

    /**
     * @return HasMany<ComboSlotOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ComboSlotOption::class)->orderBy('sort');
    }
}
