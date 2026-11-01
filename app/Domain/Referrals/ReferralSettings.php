<?php

declare(strict_types=1);

namespace App\Domain\Referrals;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * A restaurant's invite programme: on or off, the reward each side gets
 * (from the rewards catalog) and how many friends one guest can be
 * rewarded for each month. Stored under restaurants.settings.referral.
 */
final readonly class ReferralSettings
{
    public const DEFAULT_CAP = 10;

    public const MAX_CAP = 100;

    private function __construct(
        public bool $enabled,
        public ?int $referrerRewardId,
        public ?int $friendRewardId,
        public int $monthlyCap,
        private Restaurant $restaurant,
    ) {}

    public static function for(Restaurant $restaurant): self
    {
        $stored = (array) ($restaurant->settings['referral'] ?? []);

        return new self(
            (bool) ($stored['enabled'] ?? false),
            isset($stored['referrer_reward_id']) ? (int) $stored['referrer_reward_id'] : null,
            isset($stored['friend_reward_id']) ? (int) $stored['friend_reward_id'] : null,
            min(self::MAX_CAP, max(1, (int) ($stored['monthly_cap'] ?? self::DEFAULT_CAP))),
            $restaurant,
        );
    }

    /**
     * @param  array{enabled?: mixed, referrer_reward_id?: mixed, friend_reward_id?: mixed, monthly_cap?: mixed}  $values
     */
    public static function save(Restaurant $restaurant, array $values): self
    {
        $settings = (array) $restaurant->settings;
        $settings['referral'] = [
            'enabled' => (bool) ($values['enabled'] ?? false),
            'referrer_reward_id' => filled($values['referrer_reward_id'] ?? null) ? (int) $values['referrer_reward_id'] : null,
            'friend_reward_id' => filled($values['friend_reward_id'] ?? null) ? (int) $values['friend_reward_id'] : null,
            'monthly_cap' => min(self::MAX_CAP, max(1, (int) ($values['monthly_cap'] ?? self::DEFAULT_CAP))),
        ];
        $restaurant->forceFill(['settings' => $settings])->save();

        return self::for($restaurant);
    }

    /** Switched on, in the plan, and both rewards still in the catalog. */
    public function isLive(): bool
    {
        return $this->liveRewards() !== null;
    }

    /**
     * Both rewards when invites are live, in one look-up.
     *
     * @return array{referrer: Reward, friend: Reward}|null
     */
    public function liveRewards(): ?array
    {
        if (! $this->enabled || ! $this->restaurant->hasFeature(PlanFeature::Loyalty)) {
            return null;
        }

        $referrer = $this->referrerReward();
        $friend = $referrer === null ? null : $this->friendReward();

        return $referrer !== null && $friend !== null ? ['referrer' => $referrer, 'friend' => $friend] : null;
    }

    public function referrerReward(): ?Reward
    {
        return $this->reward($this->referrerRewardId);
    }

    public function friendReward(): ?Reward
    {
        return $this->reward($this->friendRewardId);
    }

    private function reward(?int $id): ?Reward
    {
        if ($id === null) {
            return null;
        }

        return Reward::withoutTenancy()
            ->with('product')
            ->where('restaurant_id', $this->restaurant->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->find($id);
    }
}
