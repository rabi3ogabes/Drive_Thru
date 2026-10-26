<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\SubscriptionCharges;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A restaurant's subscription to a plan. Prices are locked in when the
 * subscription starts or changes, so later plan price edits never surprise
 * existing clients.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property int $quantity Billed branches.
 * @property int $unit_price_minor
 * @property string $currency
 * @property BillingInterval $interval
 * @property ?CarbonImmutable $trial_ends_at
 * @property ?CarbonImmutable $current_period_start
 * @property ?CarbonImmutable $current_period_end
 * @property ?CarbonImmutable $grace_ends_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?string $cancel_reason
 * @property-read Plan $plan
 */
#[Fillable(['restaurant_id', 'plan_id', 'status', 'quantity', 'unit_price_minor', 'currency', 'interval', 'trial_ends_at', 'current_period_start', 'current_period_end', 'grace_ends_at', 'cancelled_at', 'cancel_reason'])]
#[UseFactory(SubscriptionFactory::class)]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToRestaurant, HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'quantity' => 1,
        'unit_price_minor' => 0,
        'currency' => 'QAR',
        'interval' => 'month',
        'trial_ends_at' => null,
        'current_period_start' => null,
        'current_period_end' => null,
        'grace_ends_at' => null,
        'cancelled_at' => null,
        'cancel_reason' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'interval' => BillingInterval::class,
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** The plan tier's fee plus the billed services, less the bundle discount. */
    public function amountPerPeriodMinor(): int
    {
        return app(SubscriptionCharges::class)->total($this);
    }

    /** Monthly recurring revenue this subscription contributes. */
    public function mrrMinor(): int
    {
        if (! in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            return 0;
        }

        return intdiv($this->amountPerPeriodMinor(), $this->interval->months());
    }

    public function trialDaysLeft(): ?int
    {
        if ($this->status !== SubscriptionStatus::Trialing || $this->trial_ends_at === null) {
            return null;
        }

        return max(0, (int) ceil(CarbonImmutable::now()->diffInHours($this->trial_ends_at, false) / 24));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
