<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'restaurant_name' => fake()->company().' Grill',
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+974 5'.fake()->numerify('### ####'),
            'branches_count' => fake()->numberBetween(1, 6),
            'message' => fake()->sentence(12),
            'locale' => 'ar',
            'source' => LeadSource::Website,
            'status' => LeadStatus::New,
        ];
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
