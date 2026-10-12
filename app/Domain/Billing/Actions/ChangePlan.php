<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;

/**
 * Moves a client to another plan, branch count or negotiated price. The new
 * terms apply from the next invoice; the current period is not re-billed.
 */
final class ChangePlan
{
    public function handle(Subscription $subscription, Plan $plan, ?int $quantity = null, ?int $unitPriceMinor = null): Subscription
    {
        $subscription->update([
            'plan_id' => $plan->id,
            'quantity' => max(1, $quantity ?? $subscription->quantity),
            'unit_price_minor' => max(0, $unitPriceMinor ?? $plan->price_minor ?? $subscription->unit_price_minor),
            'currency' => $plan->currency,
            'interval' => $plan->interval,
        ]);

        return $subscription->setRelation('plan', $plan);
    }
}
