<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\CampaignChannel;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Crm\Enums\Segment;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * An offer for guests who agreed to receive offers: who (segments, or
 * everyone who agreed), how (WhatsApp, SMS or push), what (the text in both
 * languages, an optional reward) and when. The counters are its results.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property ?list<string> $segments null = everyone who agreed
 * @property CampaignChannel $channel
 * @property string $message
 * @property ?int $reward_id
 * @property string $link_code
 * @property CampaignStatus $status
 * @property ?CarbonImmutable $scheduled_at
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 * @property ?CarbonImmutable $next_attempt_at
 * @property ?int $created_by
 * @property int $recipients_count
 * @property int $sent_count
 * @property int $failed_count
 * @property int $skipped_count
 * @property int $orders_count
 * @property int $revenue_minor
 * @property ?CarbonImmutable $created_at
 * @property-read ?Reward $reward
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'name', 'segments', 'channel', 'message', 'reward_id', 'scheduled_at', 'created_by'])]
class Campaign extends Model
{
    use BelongsToRestaurant, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['message'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'segments' => null,
        'reward_id' => null,
        'status' => 'draft',
        'scheduled_at' => null,
        'started_at' => null,
        'finished_at' => null,
        'next_attempt_at' => null,
        'created_by' => null,
        'recipients_count' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
        'orders_count' => 0,
        'revenue_minor' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (blank($campaign->getAttribute('link_code'))) {
                do {
                    $code = Str::lower(Str::random(8));
                } while (self::withoutTenancy()->where('link_code', $code)->exists());

                $campaign->setAttribute('link_code', $code);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'channel' => CampaignChannel::class,
            'status' => CampaignStatus::class,
            'reward_id' => 'integer',
            'scheduled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'orders_count' => 'integer',
            'revenue_minor' => 'integer',
        ];
    }

    /**
     * @return list<Segment>
     */
    public function segmentList(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?Segment => is_string($value) ? Segment::tryFrom($value) : null,
            (array) $this->segments,
        )));
    }

    /** Sent messages that brought a picked-up order, as a share of sent ones. */
    public function conversionPercent(): ?float
    {
        return $this->sent_count > 0 ? round($this->orders_count / $this->sent_count * 100, 1) : null;
    }

    /**
     * @return BelongsTo<Reward, $this>
     */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CampaignMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }
}
