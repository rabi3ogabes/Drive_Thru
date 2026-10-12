<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'quantity' => 1,
            'unit_price_minor' => 64900,
            'currency' => 'QAR',
            'interval' => 'month',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ];
    }
}
