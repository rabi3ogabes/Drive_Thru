<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One guest of one campaign. "sending" holds the guest's place under the
 * frequency cap while the provider is called.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $campaign_id
 * @property int $customer_id
 * @property string $status pending | sending | sent | failed | skipped
 * @property ?string $skip_reason no_consent | frequency_cap | no_push | cancelled
 * @property ?string $channel
 * @property string $locale
 * @property ?int $message_delivery_id
 * @property ?CarbonImmutable $sent_at
 * @property ?int $order_id
 * @property int $revenue_minor
 * @property ?CarbonImmutable $attributed_at
 * @property-read Campaign $campaign
 * @property-read Customer $customer
 * @property-read ?MessageDelivery $delivery
 */
#[Fillable(['restaurant_id', 'campaign_id', 'customer_id', 'locale'])]
class CampaignMessage extends Model
{
    use BelongsToRestaurant;

    public const PENDING = 'pending';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    /** Statuses that count towards the guest's frequency cap. */
    public const COUNTED = [self::SENDING, self::SENT];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::PENDING,
        'skip_reason' => null,
        'channel' => null,
        'locale' => 'ar',
        'message_delivery_id' => null,
        'sent_at' => null,
        'order_id' => null,
        'revenue_minor' => 0,
        'attributed_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'customer_id' => 'integer',
            'message_delivery_id' => 'integer',
            'order_id' => 'integer',
            'revenue_minor' => 'integer',
            'sent_at' => 'immutable_datetime',
            'attributed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<MessageDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
    }
}
