<?php

declare(strict_types=1);

namespace App\Domain\Referrals;

use App\Domain\Customers\Models\Customer;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\QueryException;

/**
 * Invite links between guests of one restaurant. Opening a friend's link
 * remembers who invited this browser; the next sign-in records the friend as
 * a pending referral (or rejects it at once when it is the guest's own link,
 * or they were already a guest). Rewards wait for the first picked-up order,
 * see Listeners\RewardReferral.
 */
final class Referrals
{
    /** Same alphabet as order and QR codes: no 0/O, 1/I/L. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LENGTH = 6;

    /** How long an opened invite waits for the friend to sign in. */
    private const REMEMBER_DAYS = 30;

    public function __construct(private readonly GuestDevice $device) {}

    /** The guest's own code, made on first use and never changed. */
    public function codeFor(Customer $customer): string
    {
        if ($customer->referral_code !== null) {
            return $customer->referral_code;
        }

        for ($try = 0; $try < 8; $try++) {
            $code = '';

            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            try {
                $claimed = Customer::withoutTenancy()->whereKey($customer->id)->whereNull('referral_code')->update(['referral_code' => $code]);
            } catch (QueryException) {
                continue; // taken by another guest of this restaurant
            }

            $customer->referral_code = $claimed === 1 ? $code : Customer::withoutTenancy()->whereKey($customer->id)->value('referral_code');
            $customer->syncOriginalAttribute('referral_code');

            return (string) $customer->referral_code;
        }

        throw new \RuntimeException('Could not make a unique invite code.');
    }

    public function url(Restaurant $restaurant, Customer $customer): string
    {
        return route('customer.invite', ['restaurant' => $restaurant, 'code' => $this->codeFor($customer)]);
    }

    public function referrer(Restaurant $restaurant, string $code): ?Customer
    {
        return Customer::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->where('referral_code', strtoupper($code))
            ->first();
    }

    /** The friend opened the link: remember the invite until they sign in. */
    public function remember(Restaurant $restaurant, Customer $referrer): void
    {
        session()->put($this->key($restaurant), [
            'referrer' => $referrer->id,
            'at' => now()->getTimestamp(),
        ]);
    }

    /**
     * Called after every guest sign-in. Records the referral the browser was
     * holding, if any, and returns it.
     */
    public function joined(Restaurant $restaurant, Customer $customer): ?Referral
    {
        $invite = session()->pull($this->key($restaurant));

        if (! is_array($invite) || (int) ($invite['at'] ?? 0) < now()->subDays(self::REMEMBER_DAYS)->getTimestamp()) {
            return null;
        }

        if (! ReferralSettings::for($restaurant)->isLive()) {
            return null;
        }

        $referrer = Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->find((int) ($invite['referrer'] ?? 0));

        if ($referrer === null || Referral::withoutTenancy()->where('friend_id', $customer->id)->exists()) {
            return null;
        }

        $reason = match (true) {
            $referrer->id === $customer->id => 'self',
            // Already a guest before opening the link: invites are for new guests.
            $customer->created_at !== null && $customer->created_at->getTimestamp() < (int) $invite['at'] => 'existing_guest',
            $customer->picked_up_count > 0 || $customer->orders()->exists() => 'existing_guest',
            default => null,
        };

        try {
            return Referral::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'referrer_id' => $referrer->id,
                'friend_id' => $customer->id,
                'status' => $reason === null ? Referral::PENDING : Referral::REJECTED,
                'reason' => $reason,
                'device_hash' => $this->device->hash(),
                'joined_at' => now(),
                'decided_at' => $reason === null ? null : now(),
            ]);
        } catch (QueryException) {
            return null; // the same friend, signing in twice at once
        }
    }

    /**
     * What the guest's invite card shows.
     *
     * @return array{joined: int, rewarded: int}
     */
    public function stats(Customer $customer): array
    {
        $counts = Referral::withoutTenancy()
            ->where('referrer_id', $customer->id)
            ->whereIn('status', [Referral::PENDING, Referral::REWARDED])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'joined' => (int) $counts->sum(),
            'rewarded' => (int) ($counts[Referral::REWARDED] ?? 0),
        ];
    }

    private function key(Restaurant $restaurant): string
    {
        return 'referral.'.$restaurant->id;
    }
}
