<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Badges;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;

/**
 * After each picked-up order, records the badges the guest just reached
 * (once each: a unique row, so a repeated event changes nothing) and gives
 * each badge's reward, if it has one. Nothing happens while badges are off.
 */
final class AwardBadges
{
    public function __construct(private readonly GrantReward $grant) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::PickedUp || $event->order->customer_id === null) {
            return;
        }

        $restaurant = Restaurant::query()->find($event->order->restaurant_id);
        $settings = $restaurant === null ? null : BadgeSettings::for($restaurant);
        $customer = Customer::withoutTenancy()->find($event->order->customer_id);

        if ($settings === null || ! $settings->isLive() || $customer === null) {
            return;
        }

        $now = CarbonImmutable::now();

        foreach (BadgeProgress::for($customer, $settings, $now) as $key => $row) {
            $badge = Badge::from($key);

            if (! $settings->isOn($badge) || $row['earned'] !== null || $row['current'] < $row['target']) {
                continue;
            }

            $this->award($customer, $badge, $settings, $now);
        }
    }

    private function award(Customer $customer, Badge $badge, BadgeSettings $settings, CarbonImmutable $now): void
    {
        $inserted = CustomerBadge::withoutTenancy()->insertOrIgnore([
            'restaurant_id' => $customer->restaurant_id,
            'customer_id' => $customer->id,
            'badge' => $badge->value,
            'earned_at' => $now,
        ]);

        $reward = $inserted === 1 ? $settings->reward($badge) : null;

        if ($reward === null) {
            return;
        }

        try {
            $wallet = $this->grant->handle($customer, $reward, 'badge', null);
        } catch (RewardUnavailable) {
            return;
        }

        CustomerBadge::withoutTenancy()->where('customer_id', $customer->id)->where('badge', $badge->value)->update(['wallet_reward_id' => $wallet->id]);
    }
}
