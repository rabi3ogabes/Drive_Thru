<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => ['en' => $name, 'ar' => 'مطعم '.$name],
            'status' => RestaurantStatus::Active,
            'currency' => 'QAR',
            'timezone' => 'Asia/Qatar',
            'default_locale' => 'ar',
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => RestaurantStatus::Suspended]);
    }
}
