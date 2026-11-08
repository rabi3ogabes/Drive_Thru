<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Badges;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A badge a guest earned: once each, never taken back. Written by
 * AwardBadges; seen_at is set when the guest saw the celebration.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property Badge $badge
 * @property CarbonImmutable $earned_at
 * @property ?int $wallet_reward_id
 * @property ?CarbonImmutable $seen_at
 * @property-read Customer $customer
 * @property-read ?WalletReward $walletReward
 */
#[Fillable(['restaurant_id', 'customer_id', 'badge', 'earned_at', 'wallet_reward_id', 'seen_at'])]
class CustomerBadge extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'wallet_reward_id' => null,
        'seen_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'badge' => Badge::class,
            'earned_at' => 'immutable_datetime',
            'seen_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<WalletReward, $this>
     */
    public function walletReward(): BelongsTo
    {
        return $this->belongsTo(WalletReward::class);
    }
}
