<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Menu\Concerns\BustsMenuCache;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * One choice inside a modifier group, with its price and calorie change.
 * Choosing it can open a nested group (e.g. "Large drink" → "Which drink?").
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $modifier_group_id
 * @property ?int $nested_group_id
 * @property string $name
 * @property int $price_delta_minor
 * @property ?int $calories_delta
 * @property bool $is_default
 * @property bool $is_available
 * @property int $sort
 * @property-read ModifierGroup $group
 * @property-read ?ModifierGroup $nestedGroup
 */
#[Fillable(['restaurant_id', 'modifier_group_id', 'nested_group_id', 'name', 'price_delta_minor', 'calories_delta', 'is_default', 'is_available', 'sort'])]
class ModifierOption extends Model
{
    use BelongsToRestaurant, BustsMenuCache, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'nested_group_id' => null,
        'price_delta_minor' => 0,
        'calories_delta' => null,
        'is_default' => false,
        'is_available' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_delta_minor' => 'integer',
            'calories_delta' => 'integer',
            'is_default' => 'boolean',
            'is_available' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ModifierGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    /**
     * @return BelongsTo<ModifierGroup, $this>
     */
    public function nestedGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'nested_group_id');
    }
}
