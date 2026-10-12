<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Menu\Concerns\BustsMenuCache;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A menu section (Burgers, Drinks…), optionally served only during a schedule.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property ?int $menu_schedule_id
 * @property string $name
 * @property ?string $description
 * @property ?string $icon
 * @property ?string $image_path
 * @property bool $is_visible
 * @property int $sort
 * @property-read ?MenuSchedule $schedule
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'menu_schedule_id', 'name', 'description', 'icon', 'image_path', 'is_visible', 'sort'])]
class MenuCategory extends Model
{
    use BelongsToRestaurant, BustsMenuCache, HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'menu_schedule_id' => null,
        'description' => null,
        'icon' => null,
        'image_path' => null,
        'is_visible' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MenuSchedule::class, 'menu_schedule_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path !== null ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
