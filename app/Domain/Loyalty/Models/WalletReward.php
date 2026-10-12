<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reward in a guest's wallet: available until used at checkout or expired.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property int $reward_id
 * @property string $source claimed | tier | birthday | referral | campaign | manual
 * @property string $status available | used | expired | cancelled
 * @property ?CarbonImmutable $expires_at
 * @property ?int $used_order_id
 * @property ?CarbonImmutable $created_at
 * @property-read Reward $reward
 * @property-read Customer $customer
 */
#[Fillable(['restaurant_id', 'customer_id', 'reward_id', 'source', 'status', 'expires_at', 'used_order_id'])]
class WalletReward extends Model
{
    use BelongsToRestaurant;

    public const AVAILABLE = 'available';

    public const USED = 'used';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    protected $table = 'customer_rewards';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::AVAILABLE,
        'expires_at' => null,
        'used_order_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Reward, $this>
     */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isUsable(): bool
    {
        return $this->status === self::AVAILABLE && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::AVAILABLE)->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
