<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A named set of restaurant permissions (Cashier, Kitchen, or a custom one)
 * that employees are assigned to.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property ?string $key
 * @property string $name
 * @property ?string $description
 * @property Collection<int, RestaurantPermission> $permissions
 * @property bool $is_system
 * @property int $sort
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'key', 'name', 'description', 'permissions', 'is_system', 'sort'])]
#[UseFactory(PositionFactory::class)]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use BelongsToRestaurant, HasFactory, HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'key' => null,
        'description' => null,
        'is_system' => false,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'permissions' => AsEnumCollection::of(RestaurantPermission::class),
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function grants(RestaurantPermission $permission): bool
    {
        return $this->permissions->contains($permission);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
