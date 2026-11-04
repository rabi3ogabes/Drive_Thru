<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Models\User;

/** Puts a ticket right with a reward from the catalog, straight into the guest's wallet. */
final class RewardTicket
{
    public function __construct(private readonly GrantReward $grant) {}

    /**
     * @throws TicketRefused
     */
    public function handle(SupportTicket $ticket, User $by, Reward $reward): WalletReward
    {
        if (! $by->hasRestaurantPermission($ticket->restaurant_id, RestaurantPermission::HandleSupport)) {
            throw new TicketRefused('forbidden');
        }

        try {
            $wallet = $this->grant->handle($ticket->customer()->firstOrFail(), $reward, 'support', $by);
        } catch (RewardUnavailable) {
            throw new TicketRefused('reward_unavailable');
        }

        $ticket->forceFill(['outcome' => 'reward', 'wallet_reward_id' => $wallet->id])->save();

        activity()->performedOn($ticket)->causedBy($by)->event('support.rewarded')
            ->withProperties(['restaurant_id' => $ticket->restaurant_id, 'reward_id' => $reward->id])->log('support.rewarded');

        return $wallet;
    }
}
