<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Notifications\RestaurantReactivated;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lifts a suspension. A client still inside its trial goes back to trial;
 * everyone else becomes active.
 */
final class ReactivateRestaurant
{
    public function handle(Restaurant $restaurant, ?User $actor = null): Restaurant
    {
        if (! in_array($restaurant->status, [RestaurantStatus::Suspended, RestaurantStatus::Cancelled], true)) {
            return $restaurant;
        }

        DB::transaction(function () use ($restaurant): void {
            $subscription = $restaurant->subscription;
            $stillOnTrial = $subscription?->trial_ends_at !== null && $subscription->trial_ends_at->isFuture();

            $subscription?->update([
                'status' => $stillOnTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ]);

            $restaurant->forceFill([
                'status' => $stillOnTrial ? RestaurantStatus::Trial : RestaurantStatus::Active,
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();
        });

        activity()->performedOn($restaurant)->causedBy($actor)->event('restaurant.reactivated')->log('Restaurant reactivated');

        $restaurant->owner()?->notify(new RestaurantReactivated($restaurant));

        return $restaurant;
    }
}
