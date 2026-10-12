<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A message handed to WhatsApp or SMS, and what the provider later reported
 * (sent, delivered, read, failed). The phone is stored hashed and masked;
 * the text is never stored.
 *
 * @property int $id
 * @property ?int $restaurant_id
 * @property string $channel
 * @property string $purpose
 * @property string $to_hash
 * @property string $to_masked
 * @property ?string $provider_message_id
 * @property string $status
 * @property ?string $error
 */
#[Fillable(['restaurant_id', 'channel', 'purpose', 'to_hash', 'to_masked', 'provider_message_id', 'status', 'error', 'subject_type', 'subject_id'])]
class MessageDelivery extends Model
{
    use BelongsToRestaurant;

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    /** Providers can report out of order: a delivery never goes back to an earlier state. */
    private const RANK = [self::QUEUED => 0, self::SENT => 1, self::DELIVERED => 2, self::READ => 3, self::FAILED => 4];

    /** @var array<string, mixed> */
    protected $attributes = [
        'provider_message_id' => null,
        'status' => self::QUEUED,
        'error' => null,
        'subject_type' => '',
        'subject_id' => 0,
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Applies a provider status report, keeping the furthest state reached. */
    public function report(string $status, ?string $error = null): void
    {
        if (! array_key_exists($status, self::RANK)) {
            return;
        }

        if ($status !== self::FAILED && self::RANK[$status] <= self::RANK[$this->status]) {
            return;
        }

        $this->forceFill(['status' => $status, 'error' => $error !== null ? mb_substr($error, 0, 280) : $this->error])->save();
    }
}
