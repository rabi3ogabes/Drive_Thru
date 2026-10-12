<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Notifications\RestaurantSuspended;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pauses a client: guests can no longer order and only the owner keeps access
 * to the workspace (to settle billing). Reversible with ReactivateRestaurant.
 */
final class SuspendRestaurant
{
    /** Reason stored when the billing cycle suspends an unpaid client. */
    public const BILLING_REASON = 'billing:unpaid';

    public function handle(Restaurant $restaurant, string $reason, ?User $actor = null): Restaurant
    {
        if ($restaurant->status === RestaurantStatus::Suspended) {
            return $restaurant;
        }

        DB::transaction(function () use ($restaurant, $reason): void {
            $restaurant->forceFill([
                'status' => RestaurantStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ])->save();

            $restaurant->subscription?->update(['status' => SubscriptionStatus::Suspended]);
        });

        activity()
            ->performedOn($restaurant)
            ->causedBy($actor)
            ->event('restaurant.suspended')
            ->withProperties(['reason' => $reason])
            ->log('Restaurant suspended');

        $restaurant->owner()?->notify(new RestaurantSuspended($restaurant));

        return $restaurant;
    }
}
