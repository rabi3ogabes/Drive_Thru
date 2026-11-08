<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Badges;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * Streaks and badges for one restaurant: off by default. Once switched on,
 * every badge counts unless the owner turns it off, each can unlock a
 * reward from the catalog, and the streak length is 2-8 weeks (3 by
 * default). Stored under restaurants.settings.badges.
 */
final readonly class BadgeSettings
{
    public const DEFAULT_STREAK_WEEKS = 3;

    public const MIN_STREAK_WEEKS = 2;

    public const MAX_STREAK_WEEKS = 8;

    /**
     * @param  array<string, array{on: bool, reward_id: ?int}>  $badges
     */
    private function __construct(
        public bool $enabled,
        public int $streakWeeks,
        private array $badges,
        private Restaurant $restaurant,
    ) {}

    public static function for(Restaurant $restaurant): self
    {
        $stored = (array) ($restaurant->settings['badges'] ?? []);

        return new self(
            (bool) ($stored['enabled'] ?? false),
            self::weeks($stored['streak_weeks'] ?? self::DEFAULT_STREAK_WEEKS),
            self::clean((array) ($stored['badges'] ?? [])),
            $restaurant,
        );
    }

    /**
     * @param  array<string, mixed>  $values  enabled, streak_weeks, badges: [badge => [on, reward_id]]
     */
    public static function save(Restaurant $restaurant, array $values): self
    {
        $settings = (array) $restaurant->settings;
        $settings['badges'] = [
            'enabled' => (bool) ($values['enabled'] ?? false),
            'streak_weeks' => self::weeks($values['streak_weeks'] ?? self::DEFAULT_STREAK_WEEKS),
            'badges' => self::clean((array) ($values['badges'] ?? [])),
        ];
        $restaurant->forceFill(['settings' => $settings])->save();

        return self::for($restaurant);
    }

    /** Switched on and in the plan. */
    public function isLive(): bool
    {
        return $this->enabled && $this->restaurant->hasFeature(PlanFeature::Loyalty);
    }

    public function isOn(Badge $badge): bool
    {
        return $this->badges[$badge->value]['on'];
    }

    public function target(Badge $badge): int
    {
        return $badge->target($this->streakWeeks);
    }

    /**
     * @return list<Badge>
     */
    public function active(): array
    {
        return array_values(array_filter(Badge::cases(), fn (Badge $badge): bool => $this->isOn($badge)));
    }

    public function rewardId(Badge $badge): ?int
    {
        return $this->badges[$badge->value]['reward_id'];
    }

    public function reward(Badge $badge): ?Reward
    {
        $id = $this->rewardId($badge);

        return $id === null ? null : Reward::withoutTenancy()
            ->where('restaurant_id', $this->restaurant->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled, 'streak_weeks' => $this->streakWeeks, 'badges' => $this->badges];
    }

    /**
     * Every badge, on unless switched off, with a reward id or null.
     *
     * @param  array<array-key, mixed>  $stored
     * @return array<string, array{on: bool, reward_id: ?int}>
     */
    private static function clean(array $stored): array
    {
        $badges = [];

        foreach (Badge::cases() as $badge) {
            $row = (array) ($stored[$badge->value] ?? []);
            $badges[$badge->value] = [
                'on' => (bool) ($row['on'] ?? true),
                'reward_id' => filled($row['reward_id'] ?? null) ? (int) $row['reward_id'] : null,
            ];
        }

        return $badges;
    }

    private static function weeks(mixed $value): int
    {
        return min(self::MAX_STREAK_WEEKS, max(self::MIN_STREAK_WEEKS, (int) $value));
    }
}
