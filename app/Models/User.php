<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SensitiveParameter;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Everyone who signs in with email and password: the super admin, platform
 * staff, restaurant owners and their employees. Guests (customers) are a
 * separate model and never use this table.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $phone
 * @property ?string $locale
 * @property bool $is_super_admin
 * @property ?int $platform_role_id
 * @property bool $is_active
 * @property ?string $app_authentication_secret
 * @property ?array<string> $app_authentication_recovery_codes
 * @property ?Carbon $last_login_at
 * @property ?string $last_login_ip
 */
#[Fillable(['name', 'email', 'password', 'phone', 'locale'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasDefaultTenant, HasLocalePreference, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * Mirror the column defaults so a freshly created model is complete in memory.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'phone' => null,
        'locale' => null,
        'notification_prefs' => null,
        'is_super_admin' => false,
        'platform_role_id' => null,
        'is_active' => true,
        'app_authentication_secret' => null,
        'app_authentication_recovery_codes' => null,
        'last_login_at' => null,
        'last_login_ip' => null,
    ];

    /** @var array<int, Employee|null> Memoized active employments per restaurant id. */
    private array $employmentCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'notification_prefs' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PlatformRole, $this>
     */
    public function platformRole(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class);
    }

    /**
     * All memberships across restaurants (tenant scope removed on purpose).
     *
     * @return HasMany<Employee, $this>
     */
    public function employments(): HasMany
    {
        return $this->hasMany(Employee::class)->withoutTenancy();
    }

    /**
     * @return BelongsToMany<Restaurant, $this>
     */
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'employees')
            ->withPivot(['position_id', 'is_owner', 'status'])
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function isPlatformStaff(): bool
    {
        return $this->is_super_admin || $this->platform_role_id !== null;
    }

    public function hasPlatformPermission(PlatformPermission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->is_super_admin || ($this->platformRole?->grants($permission) ?? false);
    }

    public function employmentAt(Restaurant|int $restaurant): ?Employee
    {
        $restaurantId = $restaurant instanceof Restaurant ? $restaurant->getKey() : $restaurant;

        if (! array_key_exists($restaurantId, $this->employmentCache)) {
            $this->employmentCache[$restaurantId] = Employee::withoutTenancy()
                ->with('position')
                ->where('restaurant_id', $restaurantId)
                ->where('user_id', $this->getKey())
                ->first();
        }

        return $this->employmentCache[$restaurantId];
    }

    public function hasRestaurantPermission(Restaurant|int $restaurant, RestaurantPermission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->is_super_admin) {
            return true;
        }

        return $this->employmentAt($restaurant)?->allows($permission) ?? false;
    }

    public function forgetEmploymentCache(): void
    {
        $this->employmentCache = [];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->isPlatformStaff(),
            'app' => $this->accessibleRestaurants()->isNotEmpty(),
            default => false,
        };
    }

    /**
     * @return Collection<int, Restaurant>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->accessibleRestaurants();
    }

    /**
     * Restaurants this person can open right now (active membership, and the
     * restaurant is operating unless they own it).
     *
     * @return Collection<int, Restaurant>
     */
    public function accessibleRestaurants(): Collection
    {
        return $this->activeRestaurants()
            ->orderBy('restaurants.id')
            ->get()
            ->filter(fn (Restaurant $restaurant): bool => $restaurant->isOperational() || (bool) $restaurant->getRelationValue('pivot')?->is_owner)
            ->values();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Restaurant || ! $this->is_active) {
            return false;
        }

        $employment = $this->employmentAt($tenant);

        if ($employment === null || ! $employment->isActive()) {
            return false;
        }

        // A suspended or cancelled restaurant stays open to its owner only, to settle billing.
        return $tenant->isOperational() || $employment->is_owner;
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->accessibleRestaurants()->first();
    }

    /**
     * @return BelongsToMany<Restaurant, $this>
     */
    public function activeRestaurants(): BelongsToMany
    {
        return $this->restaurants()->wherePivot('status', EmployeeStatus::Active->value);
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'is_super_admin', 'platform_role_id', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * How this person wants operations alerts: push on this device and email
     * for critical alerts are on by default; WhatsApp is opt-in.
     */
    public function wantsAlertsBy(string $channel): bool
    {
        $defaults = ['push' => true, 'email' => true, 'whatsapp' => false];
        $prefs = is_array($this->notification_prefs) ? $this->notification_prefs : [];

        return (bool) ($prefs[$channel] ?? $defaults[$channel] ?? false);
    }
}
