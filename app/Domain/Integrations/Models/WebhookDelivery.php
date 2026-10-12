<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event sent (or being retried) to one endpoint, with the receiver's
 * answer. The event id stays the same across retries and replays, so the
 * receiver can ignore duplicates. Kept for 30 days.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $webhook_endpoint_id
 * @property string $event_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property ?int $response_status
 * @property ?string $response_excerpt
 * @property ?CarbonImmutable $next_attempt_at
 * @property ?CarbonImmutable $delivered_at
 * @property ?CarbonImmutable $created_at
 * @property-read WebhookEndpoint $endpoint
 */
#[Fillable(['restaurant_id', 'webhook_endpoint_id', 'event_id', 'event', 'payload', 'status', 'attempts', 'response_status', 'response_excerpt', 'next_attempt_at', 'delivered_at'])]
class WebhookDelivery extends Model
{
    use BelongsToRestaurant, MassPrunable;

    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    /** Waits before each retry: 1 min, 5 min, 30 min, 2 h, 6 h, 12 h; then it has failed. */
    public const BACKOFF_MINUTES = [1, 5, 30, 120, 360, 720];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::PENDING,
        'attempts' => 0,
        'response_status' => null,
        'response_excerpt' => null,
        'next_attempt_at' => null,
        'delivered_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::withoutTenancy()->where('created_at', '<', now()->subDays(30));
    }
}
