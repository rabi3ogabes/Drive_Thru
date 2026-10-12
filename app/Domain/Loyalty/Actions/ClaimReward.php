<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Exceptions\NotEnoughPoints;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use Illuminate\Support\Facades\DB;

/** A guest spends points on a catalog reward; it lands in their wallet with an expiry. */
final class ClaimReward
{
    public function __construct(private readonly Points $points) {}

    /**
     * @throws RewardUnavailable
     */
    public function handle(Customer $customer, Reward $reward): WalletReward
    {
        if ($reward->restaurant_id !== $customer->restaurant_id) {
            throw RewardUnavailable::because('unavailable');
        }

        $program = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $customer->restaurant_id)->first();

        if ($program === null || ! $program->isActive()) {
            throw RewardUnavailable::because('program_off');
        }

        return DB::transaction(function () use ($customer, $reward): WalletReward {
            $locked = Reward::withoutTenancy()->whereKey($reward->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isClaimable()) {
                throw RewardUnavailable::because($locked->stock === 0 ? 'out_of_stock' : 'unavailable');
            }

            try {
                $this->points->debit($customer, LedgerType::Redeem, (int) $locked->points_cost, null, 'reward:'.$locked->id);
            } catch (NotEnoughPoints) {
                throw RewardUnavailable::because('not_enough_points');
            }

            if ($locked->stock !== null) {
                $locked->forceFill(['stock' => $locked->stock - 1])->save();
            }

            return WalletReward::withoutTenancy()->create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'reward_id' => $locked->id,
                'source' => 'claimed',
                'status' => WalletReward::AVAILABLE,
                'expires_at' => now()->addDays($locked->valid_days),
            ]);
        });
    }
}
