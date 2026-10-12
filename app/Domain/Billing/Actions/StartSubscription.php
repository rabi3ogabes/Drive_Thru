<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Puts a restaurant on a plan: a free trial when the plan (or the platform)
 * offers one, otherwise an active period that is invoiced immediately.
 */
final class StartSubscription
{
    public function __construct(private readonly IssueInvoice $issueInvoice) {}

    public function handle(
        Restaurant $restaurant,
        Plan $plan,
        int $quantity = 1,
        ?int $trialDays = null,
        ?int $unitPriceMinor = null,
    ): Subscription {
        return DB::transaction(function () use ($restaurant, $plan, $quantity, $trialDays, $unitPriceMinor): Subscription {
            $now = CarbonImmutable::now();
            $trialDays = max(0, $trialDays ?? $plan->trial_days);
            $onTrial = $trialDays > 0;

            $subscription = Subscription::create([
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
                'status' => $onTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'quantity' => max(1, $quantity),
                'unit_price_minor' => max(0, $unitPriceMinor ?? $plan->price_minor ?? 0),
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'trial_ends_at' => $onTrial ? $now->addDays($trialDays) : null,
                'current_period_start' => $now,
                'current_period_end' => $onTrial ? $now->addDays($trialDays) : $plan->interval->addTo($now),
            ]);

            $restaurant->forceFill([
                'status' => $onTrial ? RestaurantStatus::Trial : RestaurantStatus::Active,
                'currency' => $plan->currency,
            ])->save();

            if (! $onTrial && $subscription->amountPerPeriodMinor() > 0) {
                $this->issueInvoice->handle($subscription, $now, $subscription->current_period_end ?? $now);
            }

            return $subscription->setRelation('plan', $plan);
        });
    }
}
