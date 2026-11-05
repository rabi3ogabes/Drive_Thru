<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Birthdays;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A birthday gift that went to a guest: one per guest per year, and never
 * two within 365 days (see SendBirthdayGifts).
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property int $year
 * @property ?int $wallet_reward_id
 * @property CarbonImmutable $sent_at
 * @property-read Customer $customer
 * @property-read ?WalletReward $walletReward
 */
#[Fillable(['restaurant_id', 'customer_id', 'year', 'wallet_reward_id', 'sent_at'])]
class BirthdayGift extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'wallet_reward_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'sent_at' => 'immutable_datetime',
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
