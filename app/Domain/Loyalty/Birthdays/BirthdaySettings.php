<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Birthdays;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * A restaurant's birthday gift: on or off, the reward from the catalog, how
 * many days before the birthday it arrives (0-7) and whether the guest gets
 * a message. Stored under restaurants.settings.birthday; off until set.
 */
final readonly class BirthdaySettings
{
    public const DEFAULT_DAYS_BEFORE = 3;

    public const MAX_DAYS_BEFORE = 7;

    private function __construct(
        public bool $enabled,
        public ?int $rewardId,
        public int $daysBefore,
        public bool $sendMessage,
        private Restaurant $restaurant,
    ) {}

    public static function for(Restaurant $restaurant): self
    {
        $stored = (array) ($restaurant->settings['birthday'] ?? []);

        return new self(
            (bool) ($stored['enabled'] ?? false),
            isset($stored['reward_id']) ? (int) $stored['reward_id'] : null,
            self::days($stored['days_before'] ?? self::DEFAULT_DAYS_BEFORE),
            (bool) ($stored['send_message'] ?? true),
            $restaurant,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(Restaurant $restaurant, array $values): self
    {
        $settings = (array) $restaurant->settings;
        $settings['birthday'] = [
            'enabled' => (bool) ($values['enabled'] ?? false),
            'reward_id' => filled($values['reward_id'] ?? null) ? (int) $values['reward_id'] : null,
            'days_before' => self::days($values['days_before'] ?? self::DEFAULT_DAYS_BEFORE),
            'send_message' => (bool) ($values['send_message'] ?? true),
        ];
        $restaurant->forceFill(['settings' => $settings])->save();

        return self::for($restaurant);
    }

    /** Switched on, in the plan, and the reward still in the catalog. */
    public function isLive(): bool
    {
        return $this->enabled && $this->restaurant->hasFeature(PlanFeature::Loyalty) && $this->reward() !== null;
    }

    public function reward(): ?Reward
    {
        return $this->rewardId === null ? null : Reward::withoutTenancy()
            ->with('product')
            ->where('restaurant_id', $this->restaurant->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->find($this->rewardId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled, 'reward_id' => $this->rewardId, 'days_before' => $this->daysBefore, 'send_message' => $this->sendMessage];
    }

    private static function days(mixed $value): int
    {
        return min(self::MAX_DAYS_BEFORE, max(0, (int) $value));
    }
}
