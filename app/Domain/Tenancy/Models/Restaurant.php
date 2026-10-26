<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Access\Models\Position;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Branches\Enums\MenuLinkMode;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\MenuSchedule;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Models\Order;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Models\RestaurantService;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\RestaurantFactory;
use DateTimeInterface;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A client of the platform: one restaurant brand with its branches, team,
 * menu, customers and orders. This is the tenant.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property RestaurantStatus $status
 * @property string $currency
 * @property string $timezone
 * @property string $default_locale
 * @property ?string $logo_path
 * @property ?string $accent_color
 * @property ?string $cover_path
 * @property int $tax_rate_bp
 * @property int $service_fee_bp
 * @property MenuLinkMode $menu_link_mode
 * @property bool $order_override_enabled
 * @property int $cancellation_window_minutes
 * @property ?array<string, mixed> $menu_layout
 * @property ?array<string, mixed> $settings
 * @property ?string $legal_name
 * @property ?string $contact_email
 * @property ?string $contact_phone
 * @property ?string $tax_number
 * @property ?string $billing_address
 * @property ?string $internal_notes
 * @property ?array<string, bool> $feature_overrides
 * @property ?CarbonImmutable $suspended_at
 * @property ?string $suspension_reason
 * @property ?string $quiet_hours_start
 * @property ?string $quiet_hours_end
 * @property ?CarbonImmutable $created_at
 * @property-read ?Subscription $subscription
 */
#[Fillable(['slug', 'name', 'logo_path', 'cover_path', 'accent_color', 'tax_rate_bp', 'service_fee_bp', 'menu_link_mode', 'order_override_enabled', 'cancellation_window_minutes', 'menu_layout', 'currency', 'timezone', 'default_locale', 'status', 'settings', 'legal_name', 'contact_email', 'contact_phone', 'tax_number', 'billing_address', 'internal_notes', 'feature_overrides', 'suspended_at', 'suspension_reason', 'quiet_hours_start', 'quiet_hours_end'])]
#[UseFactory(RestaurantFactory::class)]
class Restaurant extends Model implements HasAvatar, HasName
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'currency' => 'QAR',
        'timezone' => 'Asia/Qatar',
        'default_locale' => 'ar',
        'logo_path' => null,
        'accent_color' => null,
        'cover_path' => null,
        'tax_rate_bp' => 0,
        'service_fee_bp' => 0,
        'menu_link_mode' => 'single',
        'order_override_enabled' => false,
        'cancellation_window_minutes' => 2,
        'quiet_hours_start' => null,
        'quiet_hours_end' => null,
        'menu_layout' => null,
        'settings' => null,
        'legal_name' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'tax_number' => null,
        'billing_address' => null,
        'internal_notes' => null,
        'feature_overrides' => null,
        'suspended_at' => null,
        'suspension_reason' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
            'settings' => 'array',
            'menu_layout' => 'array',
            'tax_rate_bp' => 'integer',
            'service_fee_bp' => 'integer',
            'menu_link_mode' => MenuLinkMode::class,
            'order_override_enabled' => 'boolean',
            'cancellation_window_minutes' => 'integer',
            'feature_overrides' => 'array',
            'suspended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /**
     * @return HasMany<ModifierGroup, $this>
     */
    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }

    /**
     * @return HasMany<MenuSchedule, $this>
     */
    public function menuSchedules(): HasMany
    {
        return $this->hasMany(MenuSchedule::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path !== null ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employees')
            ->withPivot(['position_id', 'is_owner', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasOne<LoyaltyProgram, $this>
     */
    public function loyaltyProgram(): HasOne
    {
        return $this->hasOne(LoyaltyProgram::class);
    }

    /**
     * The current subscription (the most recent one).
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function ownerEmployment(): HasOne
    {
        return $this->hasOne(Employee::class)->where('is_owner', true)->oldestOfMany();
    }

    /** The restaurant owner (first owner membership), if any. */
    public function owner(): ?User
    {
        return once(fn (): ?User => $this->ownerEmployment()->with('user')->first()?->user);
    }

    public function plan(): ?Plan
    {
        return $this->subscription?->plan;
    }

    /**
     * Plan features, adjusted by per-client overrides set by the platform.
     */
    public function hasFeature(PlanFeature $feature): bool
    {
        $override = $this->feature_overrides[$feature->value] ?? null;

        if (is_bool($override)) {
            return $override;
        }

        // Features of a service come with the service, whatever the plan tier.
        $service = $feature->service();

        if ($service !== null) {
            return $this->hasService($service);
        }

        return $this->plan()?->includes($feature) ?? false;
    }

    /**
     * @return HasMany<RestaurantService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(RestaurantService::class);
    }

    /** The restaurant's record for a service (whatever its status), loaded once per request. */
    public function service(Service $service): ?RestaurantService
    {
        $this->loadMissing(['services' => fn ($query) => $query->withoutTenancy()]);

        return $this->services->first(fn (RestaurantService $row): bool => $row->service === $service);
    }

    /** True while the service is active, on demo, or cancelling but still paid for. */
    public function hasService(Service $service): bool
    {
        return $this->service($service)?->isOn() ?? false;
    }

    /** Up to two initials for avatars and cards, from the name in the current language. */
    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo_path !== null ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Whether non-urgent alerts should stay silent right now (local time).
     * A range like 23:00–08:00 crosses midnight.
     */
    public function inQuietHours(?DateTimeInterface $at = null): bool
    {
        if ($this->quiet_hours_start === null || $this->quiet_hours_end === null || $this->quiet_hours_start === $this->quiet_hours_end) {
            return false;
        }

        $now = CarbonImmutable::instance($at ?? now())->setTimezone($this->timezone)->format('H:i');

        return $this->quiet_hours_start < $this->quiet_hours_end
            ? $now >= $this->quiet_hours_start && $now < $this->quiet_hours_end
            : $now >= $this->quiet_hours_start || $now < $this->quiet_hours_end;
    }
}
