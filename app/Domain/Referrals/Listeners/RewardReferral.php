<?php

declare(strict_types=1);

namespace App\Domain\Referrals\Listeners;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Referrals\ReferralSettings;
use App\Support\Digits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The friend's first picked-up order settles their referral: the fraud
 * checks run, then both guests get their reward (source "referral"), once.
 * A cancelled order settles nothing; the referral waits for the next one.
 */
final class RewardReferral
{
    /** Enough of the inviter's past orders to know their cars. */
    private const ORDERS_CHECKED = 200;

    public function __construct(private readonly GrantReward $grant) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if ($event->to !== OrderStatus::PickedUp || $order->customer_id === null) {
            return;
        }

        if (! Referral::withoutTenancy()->where('friend_id', $order->customer_id)->where('status', Referral::PENDING)->exists()) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $referral = Referral::withoutTenancy()
                ->with(['referrer', 'friend', 'restaurant'])
                ->where('friend_id', $order->customer_id)
                ->where('status', Referral::PENDING)
                ->lockForUpdate()
                ->first();

            if ($referral === null) {
                return;
            }

            $settings = ReferralSettings::for($referral->restaurant);
            $reason = $this->problem($referral, $order, $settings);

            if ($reason !== null) {
                $referral->forceFill(['status' => Referral::REJECTED, 'reason' => $reason, 'order_id' => $order->id, 'decided_at' => now()])->save();

                return;
            }

            $referrerReward = $this->grant->handle($referral->referrer, $settings->referrerReward() ?? throw new \LogicException('Checked above.'), 'referral', null);
            $friendReward = $this->grant->handle($referral->friend, $settings->friendReward() ?? throw new \LogicException('Checked above.'), 'referral', null);

            $referral->forceFill([
                'status' => Referral::REWARDED,
                'order_id' => $order->id,
                'referrer_reward_id' => $referrerReward->id,
                'friend_reward_id' => $friendReward->id,
                'decided_at' => now(),
            ])->save();
        });
    }

    private function problem(Referral $referral, Order $order, ReferralSettings $settings): ?string
    {
        return match (true) {
            ! $settings->isLive() => 'programme_off',
            $this->sameDevice($referral) => 'same_device',
            $this->sameCar($referral, $order) => 'same_car',
            $this->overCap($referral, $settings) => 'monthly_cap',
            default => null,
        };
    }

    private function sameDevice(Referral $referral): bool
    {
        $friend = CustomerSession::withoutTenancy()->where('customer_id', $referral->friend_id)->whereNotNull('device_hash')->pluck('device_hash')
            ->push($referral->device_hash)->filter()->unique();

        return $friend->isNotEmpty() && CustomerSession::withoutTenancy()
            ->where('customer_id', $referral->referrer_id)
            ->whereIn('device_hash', $friend->all())
            ->exists();
    }

    private function sameCar(Referral $referral, Order $order): bool
    {
        $friend = $this->plates($referral->friend)->push($this->plate($order->vehicle['plate'] ?? null))->filter()->unique();

        if ($friend->isEmpty()) {
            return false;
        }

        $referrer = $this->plates($referral->referrer)->merge(
            Order::withoutTenancy()->where('customer_id', $referral->referrer_id)->whereNotNull('vehicle')
                ->latest('id')->limit(self::ORDERS_CHECKED)->pluck('vehicle')
                ->map(fn (mixed $vehicle): ?string => $this->plate(is_array($vehicle) ? ($vehicle['plate'] ?? null) : null)),
        )->filter();

        return $referrer->intersect($friend)->isNotEmpty();
    }

    /**
     * @return Collection<int, string|null>
     */
    private function plates(Customer $customer): Collection
    {
        return CustomerVehicle::withoutTenancy()->where('customer_id', $customer->id)->pluck('plate')
            ->map(fn (mixed $plate): ?string => $this->plate($plate))
            ->values();
    }

    /** "123 456", "١٢٣٤٥٦" and "123456" are the same plate. */
    private function plate(mixed $plate): ?string
    {
        if (! is_string($plate)) {
            return null;
        }

        $plate = strtoupper((string) preg_replace('/[^\p{L}\p{N}]+/u', '', Digits::toLatin($plate)));

        return $plate === '' ? null : $plate;
    }

    private function overCap(Referral $referral, ReferralSettings $settings): bool
    {
        $monthStart = now()->setTimezone($referral->restaurant->timezone)->startOfMonth()->utc();

        return Referral::withoutTenancy()
            ->where('referrer_id', $referral->referrer_id)
            ->where('status', Referral::REWARDED)
            ->where('decided_at', '>=', $monthStart)
            ->count() >= $settings->monthlyCap;
    }
}
