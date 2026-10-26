<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A line in a card's history: stamps added at pickup or at the counter,
 * taken back after a cancellation, or a full card turned into a reward.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $stamp_card_id
 * @property ?int $order_id
 * @property int $delta
 * @property string $source order | counter | reversal | completed | expired
 * @property ?int $wallet_reward_id
 * @property ?int $actor_id
 * @property ?string $note
 * @property CarbonImmutable $created_at
 */
#[Fillable(['restaurant_id', 'stamp_card_id', 'order_id', 'delta', 'source', 'wallet_reward_id', 'actor_id', 'note'])]
class StampEvent extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'order_id' => null,
        'wallet_reward_id' => null,
        'actor_id' => null,
        'note' => null,
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
