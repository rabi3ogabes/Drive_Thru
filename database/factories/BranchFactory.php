<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $city = fake()->randomElement(['Lusail', 'West Bay', 'Al Wakra', 'Al Sadd', 'The Pearl']);

        return [
            'restaurant_id' => Restaurant::factory(),
            'code' => Str::slug($city).'-'.fake()->unique()->numberBetween(1, 9999),
            'name' => ['en' => $city, 'ar' => $city],
            'city' => ['en' => 'Doha', 'ar' => 'الدوحة'],
            'is_open' => true,
            'avg_prep_minutes' => 10,
        ];
    }

    public function closed(): static
    {
        return $this->state(['is_open' => false]);
    }
}
