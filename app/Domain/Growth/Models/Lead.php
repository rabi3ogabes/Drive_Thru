<?php

declare(strict_types=1);

namespace App\Domain\Growth\Models;

use App\Domain\Billing\Models\Plan;
use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\LeadFactory;
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
 * A restaurant that asked to join the platform, from the website or entered
 * by the sales team, moving through the pipeline until won or lost.
 *
 * @property int $id
 * @property string $restaurant_name
 * @property string $contact_name
 * @property string $email
 * @property ?string $phone
 * @property int $branches_count
 * @property ?int $plan_id
 * @property ?string $message
 * @property string $locale
 * @property LeadSource $source
 * @property LeadStatus $status
 * @property ?int $assigned_to
 * @property ?CarbonImmutable $follow_up_at
 * @property ?string $lost_reason
 * @property ?int $converted_restaurant_id
 * @property ?CarbonImmutable $contacted_at
 * @property ?CarbonImmutable $converted_at
 * @property ?list<string> $services services asked about (Service values)
 * @property ?CarbonImmutable $created_at
 */
#[Fillable(['restaurant_name', 'contact_name', 'email', 'phone', 'branches_count', 'plan_id', 'message', 'locale', 'source', 'status', 'assigned_to', 'follow_up_at', 'lost_reason', 'converted_restaurant_id', 'contacted_at', 'converted_at', 'ip_hash', 'services'])]
#[UseFactory(LeadFactory::class)]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'phone' => null,
        'branches_count' => 1,
        'plan_id' => null,
        'message' => null,
        'locale' => 'ar',
        'source' => 'website',
        'status' => 'new',
        'assigned_to' => null,
        'follow_up_at' => null,
        'lost_reason' => null,
        'converted_restaurant_id' => null,
        'contacted_at' => null,
        'converted_at' => null,
        'ip_hash' => null,
        'services' => null,
    ];

    protected function casts(): array
    {
        return [
            'branches_count' => 'integer',
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'follow_up_at' => 'immutable_datetime',
            'contacted_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'services' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function convertedRestaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'converted_restaurant_id');
    }

    /**
     * @return HasMany<LeadNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->with('author')->latest('created_at')->latest('id');
    }

    public function isFollowUpDue(): bool
    {
        return $this->status->isOpen() && $this->follow_up_at !== null && $this->follow_up_at->isPast();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', LeadStatus::open());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'follow_up_at', 'lost_reason', 'converted_restaurant_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
