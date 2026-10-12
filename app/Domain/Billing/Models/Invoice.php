<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property ?int $subscription_id
 * @property string $number
 * @property InvoiceStatus $status
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $tax_rate_bp
 * @property int $tax_minor
 * @property int $total_minor
 * @property ?CarbonImmutable $period_start
 * @property ?CarbonImmutable $period_end
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $due_at
 * @property ?CarbonImmutable $paid_at
 * @property ?CarbonImmutable $voided_at
 * @property ?string $payment_method
 * @property ?string $payment_reference
 * @property ?string $notes
 */
#[Fillable(['restaurant_id', 'subscription_id', 'number', 'status', 'currency', 'subtotal_minor', 'tax_rate_bp', 'tax_minor', 'total_minor', 'period_start', 'period_end', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'payment_method', 'payment_reference', 'notes'])]
#[UseFactory(InvoiceFactory::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToRestaurant, HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'subscription_id' => null,
        'subtotal_minor' => 0,
        'tax_rate_bp' => 0,
        'tax_minor' => 0,
        'total_minor' => 0,
        'period_start' => null,
        'period_end' => null,
        'paid_at' => null,
        'voided_at' => null,
        'payment_method' => null,
        'payment_reference' => null,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal_minor' => 'integer',
            'tax_rate_bp' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withoutTenancy();
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Open && $this->due_at->isPast();
    }

    public function formattedTotal(?string $locale = null): string
    {
        return Money::format($this->total_minor, $this->currency, $locale);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Open)->where('due_at', '<', now());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'paid_at', 'voided_at', 'payment_method', 'payment_reference'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
