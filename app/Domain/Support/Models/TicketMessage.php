<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a ticket's conversation: from the guest, or from staff (who
 * also reach the guest by WhatsApp or SMS; the delivery is kept).
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $support_ticket_id
 * @property string $author guest | staff
 * @property ?int $user_id
 * @property string $body
 * @property ?int $message_delivery_id
 * @property CarbonImmutable $created_at
 * @property-read ?User $user
 * @property-read ?MessageDelivery $delivery
 */
#[Fillable(['restaurant_id', 'support_ticket_id', 'author', 'user_id', 'body', 'message_delivery_id', 'created_at'])]
class TicketMessage extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    public const GUEST = 'guest';

    public const STAFF = 'staff';

    /** Longest message, from either side. */
    public const LENGTH = 500;

    /** @var array<string, mixed> */
    protected $attributes = [
        'user_id' => null,
        'message_delivery_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    public function fromGuest(): bool
    {
        return $this->author === self::GUEST;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MessageDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
    }
}
