<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'key' => 'plan-'.Str::lower(Str::random(6)),
            'name' => ['en' => 'Growth', 'ar' => 'النمو'],
            'price_minor' => 64900,
            'currency' => 'QAR',
            'interval' => BillingInterval::Monthly,
            'trial_days' => 14,
            'limits' => ['branches' => 10, 'employees' => 50],
            'features' => [PlanFeature::Loyalty, PlanFeature::Promotions],
        ];
    }

    public function withoutTrial(): static
    {
        return $this->state(['trial_days' => 0]);
    }

    public function custom(): static
    {
        return $this->state(['price_minor' => null]);
    }
}
