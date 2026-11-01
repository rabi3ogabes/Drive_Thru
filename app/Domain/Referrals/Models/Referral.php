<?php

declare(strict_types=1);

namespace App\Domain\Referrals\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A friend who joined through a guest's invite. Pending until the friend's
 * first picked-up order; then both get their reward, or it is rejected with
 * the reason (same phone, same car, already a guest, over the monthly cap).
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $referrer_id
 * @property int $friend_id
 * @property string $status pending | rewarded | rejected
 * @property ?string $reason see REASONS
 * @property ?string $device_hash
 * @property ?int $order_id
 * @property ?int $referrer_reward_id
 * @property ?int $friend_reward_id
 * @property CarbonImmutable $joined_at
 * @property ?CarbonImmutable $decided_at
 * @property ?CarbonImmutable $created_at
 * @property-read Customer $referrer
 * @property-read Customer $friend
 * @property-read ?Order $order
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'referrer_id', 'friend_id', 'status', 'reason', 'device_hash', 'order_id', 'referrer_reward_id', 'friend_reward_id', 'joined_at', 'decided_at'])]
class Referral extends Model
{
    use BelongsToRestaurant;

    public const PENDING = 'pending';

    public const REWARDED = 'rewarded';

    public const REJECTED = 'rejected';

    public const STATUSES = [self::PENDING, self::REWARDED, self::REJECTED];

    public const REASONS = ['self', 'existing_guest', 'same_device', 'same_car', 'monthly_cap', 'programme_off'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::PENDING,
        'reason' => null,
        'device_hash' => null,
        'order_id' => null,
        'referrer_reward_id' => null,
        'friend_reward_id' => null,
        'decided_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function friend(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'friend_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<WalletReward, $this>
     */
    public function referrerReward(): BelongsTo
    {
        return $this->belongsTo(WalletReward::class, 'referrer_reward_id');
    }

    /**
     * @return BelongsTo<WalletReward, $this>
     */
    public function friendReward(): BelongsTo
    {
        return $this->belongsTo(WalletReward::class, 'friend_reward_id');
    }
}
