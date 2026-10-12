<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\PlanFeature;
use App\Support\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A subscription plan sold per branch (Starter, Growth, Enterprise...).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property ?string $description
 * @property ?int $price_minor Per branch per interval; null means custom pricing.
 * @property string $currency
 * @property BillingInterval $interval
 * @property int $trial_days
 * @property array{branches?: ?int, employees?: ?int} $limits
 * @property Collection<int, PlanFeature> $features
 * @property bool $is_public
 * @property bool $is_highlighted
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['key', 'name', 'description', 'price_minor', 'currency', 'interval', 'trial_days', 'limits', 'features', 'is_public', 'is_highlighted', 'is_active', 'sort'])]
#[UseFactory(PlanFactory::class)]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'description' => null,
        'price_minor' => null,
        'currency' => 'QAR',
        'interval' => 'month',
        'trial_days' => 14,
        'limits' => '{}',
        'features' => '[]',
        'is_public' => true,
        'is_highlighted' => false,
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'interval' => BillingInterval::class,
            'trial_days' => 'integer',
            'limits' => 'array',
            'features' => AsEnumCollection::of(PlanFeature::class),
            'is_public' => 'boolean',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->withoutTenancy();
    }

    /** Null means unlimited. */
    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function includes(PlanFeature $feature): bool
    {
        return $this->features->contains($feature);
    }

    public function isCustomPriced(): bool
    {
        return $this->price_minor === null;
    }

    public function formattedPrice(?string $locale = null): string
    {
        return $this->price_minor === null
            ? __('billing.custom_pricing', [], $locale)
            : Money::format($this->price_minor, $this->currency, $locale);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
