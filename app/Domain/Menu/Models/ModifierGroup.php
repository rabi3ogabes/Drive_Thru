<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Menu\Concerns\BustsMenuCache;
use App\Domain\Menu\Enums\ModifierKind;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A reusable set of options ("Size", "Sauces", "Remove ingredients")
 * attached to many products. min 0 = optional; max null = no upper limit;
 * min = max = 1 is a single choice.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property ModifierKind $kind
 * @property int $min_select
 * @property ?int $max_select
 * @property int $sort
 * @property-read Collection<int, ModifierOption> $options
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'name', 'kind', 'min_select', 'max_select', 'sort'])]
class ModifierGroup extends Model
{
    use BelongsToRestaurant, BustsMenuCache, HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => 'choice',
        'min_select' => 0,
        'max_select' => null,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => ModifierKind::class,
            'min_select' => 'integer',
            'max_select' => 'integer',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<ModifierOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function isRequired(): bool
    {
        return $this->min_select > 0;
    }

    public function isSingleChoice(): bool
    {
        return $this->max_select === 1;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
