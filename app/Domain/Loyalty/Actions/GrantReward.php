<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives a guest a reward for free (birthday, tier, referral, campaign, or by
 * hand). Bonus points go straight into the balance; anything else waits in
 * the wallet.
 */
final class GrantReward
{
    public const SOURCES = ['tier', 'birthday', 'referral', 'campaign', 'stamps', 'manual'];

    public function __construct(private readonly Points $points) {}

    /**
     * @throws RewardUnavailable
     */
    public function handle(Customer $customer, Reward $reward, string $source, ?User $by): WalletReward
    {
        if ($reward->restaurant_id !== $customer->restaurant_id || ! $reward->is_active || $reward->archived_at !== null) {
            throw RewardUnavailable::because('unavailable');
        }

        return DB::transaction(function () use ($customer, $reward, $source, $by): WalletReward {
            $bonus = $reward->type === RewardType::BonusPoints;

            if ($bonus && ($reward->value ?? 0) > 0) {
                $this->points->credit($customer, LedgerType::Bonus, (int) $reward->value, null, $source.':reward:'.$reward->id, $by);
            }

            $wallet = WalletReward::withoutTenancy()->create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'reward_id' => $reward->id,
                'source' => in_array($source, self::SOURCES, true) ? $source : 'manual',
                'status' => $bonus ? WalletReward::USED : WalletReward::AVAILABLE,
                'expires_at' => $bonus ? null : now()->addDays($reward->valid_days),
            ]);

            if ($by !== null) {
                activity()->performedOn($customer)->causedBy($by)->event('loyalty.reward_granted')
                    ->withProperties(['reward_id' => $reward->id, 'source' => $source])->log('loyalty.reward_granted');
            }

            return $wallet;
        });
    }
}
