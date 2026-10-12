<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Branches\Enums\StationKind;
use App\Domain\Menu\Concerns\BustsMenuCache;
use App\Domain\Menu\Enums\Allergen;
use App\Domain\Menu\Enums\ProductTag;
use App\Domain\Menu\Enums\ProductType;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Something on the menu: a single item or a combo (meal). Prices are stored
 * in minor units; the discount is a whole percentage off the base price.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property ?int $menu_category_id
 * @property ?int $menu_schedule_id
 * @property ProductType $type
 * @property ?string $sku
 * @property string $name
 * @property ?string $description
 * @property ?string $ingredients
 * @property int $price_minor
 * @property int $discount_percent
 * @property ?int $calories
 * @property ?int $prep_minutes
 * @property ?StationKind $station
 * @property ?string $image_path
 * @property ?array<string, array<string, string>> $image_variants
 * @property Collection<int, Allergen> $allergens
 * @property Collection<int, ProductTag> $tags
 * @property bool $is_popular
 * @property bool $is_new
 * @property bool $is_limited
 * @property bool $is_available
 * @property ?int $meal_product_id
 * @property int $sort
 * @property-read ?MenuCategory $category
 * @property-read ?MenuSchedule $schedule
 * @property-read ?Product $meal
 * @property-read Restaurant $restaurant
 */
#[Fillable([
    'restaurant_id', 'menu_category_id', 'menu_schedule_id', 'type', 'sku', 'name', 'description', 'ingredients',
    'price_minor', 'discount_percent', 'calories', 'prep_minutes', 'station', 'image_path', 'image_variants', 'allergens', 'tags',
    'is_popular', 'is_new', 'is_limited', 'is_available', 'meal_product_id', 'sort',
])]
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToRestaurant, BustsMenuCache, HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'ingredients'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'menu_category_id' => null,
        'menu_schedule_id' => null,
        'type' => 'item',
        'sku' => null,
        'description' => null,
        'ingredients' => null,
        'discount_percent' => 0,
        'calories' => null,
        'prep_minutes' => null,
        'station' => null,
        'image_path' => null,
        'image_variants' => null,
        'allergens' => '[]',
        'tags' => '[]',
        'is_popular' => false,
        'is_new' => false,
        'is_limited' => false,
        'is_available' => true,
        'meal_product_id' => null,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'price_minor' => 'integer',
            'discount_percent' => 'integer',
            'calories' => 'integer',
            'prep_minutes' => 'integer',
            'station' => StationKind::class,
            'image_variants' => 'array',
            'allergens' => AsEnumCollection::of(Allergen::class),
            'tags' => AsEnumCollection::of(ProductTag::class),
            'is_popular' => 'boolean',
            'is_new' => 'boolean',
            'is_limited' => 'boolean',
            'is_available' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * @return BelongsTo<MenuSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MenuSchedule::class, 'menu_schedule_id');
    }

    /**
     * @return BelongsToMany<ModifierGroup, $this>
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class)->withPivot('sort')->orderByPivot('sort');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function recommendations(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_recommendations', 'product_id', 'recommended_product_id')
            ->withPivot('sort')
            ->orderByPivot('sort');
    }

    /**
     * @return HasMany<ComboSlot, $this>
     */
    public function comboSlots(): HasMany
    {
        return $this->hasMany(ComboSlot::class, 'combo_product_id')->orderBy('sort');
    }

    /**
     * The meal this item can be upgraded to ("Make it a meal").
     *
     * @return BelongsTo<Product, $this>
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'meal_product_id');
    }

    /**
     * @return HasMany<BranchProduct, $this>
     */
    public function branchSettings(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    public function isCombo(): bool
    {
        return $this->type === ProductType::Combo;
    }

    /** The guest price after the product's own discount, before any branch override. */
    public function finalPriceMinor(?int $basePriceMinor = null): int
    {
        $base = $basePriceMinor ?? $this->price_minor;

        return (int) round($base * (100 - $this->discount_percent) / 100);
    }

    /**
     * The best image for a width: WebP variant when generated, else the original.
     */
    public function imageUrl(int $width = 800, string $format = 'webp'): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        $variants = $this->image_variants[$format] ?? [];
        $path = $variants === [] ? $this->image_path : $this->closestVariant($variants, $width);

        return Storage::disk('public')->url($path);
    }

    /**
     * @param  array<string, string>  $variants  width => path
     */
    private function closestVariant(array $variants, int $width): string
    {
        ksort($variants, SORT_NUMERIC);

        foreach ($variants as $size => $path) {
            if ((int) $size >= $width) {
                return $path;
            }
        }

        return (string) end($variants);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'price_minor', 'discount_percent', 'is_available', 'menu_category_id', 'type'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
