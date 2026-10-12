<?php

declare(strict_types=1);

namespace App\Domain\Branches\Models;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Enums\BranchState;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * One drive-thru location of a restaurant: where it is, when it is open, how
 * much it can take, and how arrivals are detected.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $code
 * @property string $name
 * @property ?string $city
 * @property ?string $address
 * @property ?list<string> $phones
 * @property ?string $maps_url
 * @property ?string $latitude
 * @property ?string $longitude
 * @property ?string $logo_path
 * @property bool $use_restaurant_logo
 * @property bool $is_open
 * @property int $avg_prep_minutes
 * @property ?int $capacity_per_slot
 * @property ?CarbonImmutable $paused_until
 * @property int $busy_extra_minutes
 * @property ?CarbonImmutable $busy_until
 * @property bool $tracking_enabled
 * @property ?array<string, string> $printers
 * @property bool $auto_print
 * @property int $arrival_radius_m
 * @property int $approach_radius_m
 * @property int $avg_speed_kmh
 * @property int $location_ping_seconds
 * @property bool $auto_arrival
 * @property int $sort
 * @property-read Restaurant $restaurant
 */
#[Fillable([
    'restaurant_id', 'code', 'name', 'city', 'address', 'phones', 'maps_url', 'latitude', 'longitude',
    'logo_path', 'use_restaurant_logo', 'is_open', 'avg_prep_minutes', 'capacity_per_slot',
    'paused_until', 'busy_extra_minutes', 'busy_until', 'tracking_enabled', 'arrival_radius_m',
    'approach_radius_m', 'avg_speed_kmh', 'location_ping_seconds', 'auto_arrival', 'sort', 'printers', 'auto_print',
])]
#[UseFactory(BranchFactory::class)]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToRestaurant, HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'city', 'address'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'city' => null,
        'address' => null,
        'phones' => null,
        'maps_url' => null,
        'latitude' => null,
        'longitude' => null,
        'logo_path' => null,
        'use_restaurant_logo' => true,
        'is_open' => true,
        'avg_prep_minutes' => 10,
        'capacity_per_slot' => null,
        'paused_until' => null,
        'busy_extra_minutes' => 0,
        'busy_until' => null,
        'tracking_enabled' => true,
        'arrival_radius_m' => 60,
        'approach_radius_m' => 1500,
        'avg_speed_kmh' => 35,
        'location_ping_seconds' => 20,
        'auto_arrival' => true,
        'sort' => 0,
        'printers' => null,
        'auto_print' => false,
    ];

    protected function casts(): array
    {
        return [
            'phones' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'use_restaurant_logo' => 'boolean',
            'is_open' => 'boolean',
            'avg_prep_minutes' => 'integer',
            'capacity_per_slot' => 'integer',
            'paused_until' => 'immutable_datetime',
            'busy_extra_minutes' => 'integer',
            'busy_until' => 'immutable_datetime',
            'tracking_enabled' => 'boolean',
            'arrival_radius_m' => 'integer',
            'approach_radius_m' => 'integer',
            'avg_speed_kmh' => 'integer',
            'location_ping_seconds' => 'integer',
            'auto_arrival' => 'boolean',
            'sort' => 'integer',
            'printers' => 'array',
            'auto_print' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BranchHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(BranchHour::class)->orderBy('weekday')->orderBy('opens_at');
    }

    /**
     * @return HasMany<BranchClosure, $this>
     */
    public function closures(): HasMany
    {
        return $this->hasMany(BranchClosure::class)->orderBy('starts_on');
    }

    /**
     * @return HasMany<Lane, $this>
     */
    public function lanes(): HasMany
    {
        return $this->hasMany(Lane::class)->orderBy('sort');
    }

    /**
     * @return HasMany<Station, $this>
     */
    public function stations(): HasMany
    {
        return $this->hasMany(Station::class)->orderBy('sort');
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Employees assigned to this branch specifically (not those covering all branches).
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    public function state(?CarbonInterface $at = null): BranchState
    {
        return app(BranchSchedule::class)->state($this, $at);
    }

    public function isPaused(?CarbonInterface $at = null): bool
    {
        return $this->paused_until !== null && $this->paused_until->greaterThan($at ?? now());
    }

    public function isBusy(?CarbonInterface $at = null): bool
    {
        return $this->busy_extra_minutes > 0 && $this->busy_until !== null && $this->busy_until->greaterThan($at ?? now());
    }

    /** Minutes a guest should expect right now, including busy mode. */
    public function currentPrepMinutes(?CarbonInterface $at = null): int
    {
        return $this->avg_prep_minutes + ($this->isBusy($at) ? $this->busy_extra_minutes : 0);
    }

    /** The branch's own logo, or the restaurant's when it uses the brand logo. */
    public function logoUrl(): ?string
    {
        $path = $this->use_restaurant_logo || $this->logo_path === null
            ? $this->restaurant?->logo_path
            : $this->logo_path;

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
