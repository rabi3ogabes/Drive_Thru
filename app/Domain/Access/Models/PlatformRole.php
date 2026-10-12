<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\PlatformPermission;
use App\Models\User;
use Database\Factories\PlatformRoleFactory;
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
 * A permission set for platform staff (Support, Sales, Finance...).
 *
 * @property int $id
 * @property ?string $key
 * @property string $name
 * @property ?string $description
 * @property Collection<int, PlatformPermission> $permissions
 * @property bool $is_system
 */
#[Fillable(['key', 'name', 'description', 'permissions', 'is_system'])]
#[UseFactory(PlatformRoleFactory::class)]
class PlatformRole extends Model
{
    /** @use HasFactory<PlatformRoleFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_system' => false,
    ];

    protected function casts(): array
    {
        return [
            'permissions' => AsEnumCollection::of(PlatformPermission::class),
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function grants(PlatformPermission $permission): bool
    {
        return $this->permissions->contains($permission);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
