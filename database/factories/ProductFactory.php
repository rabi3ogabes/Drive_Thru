<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Menu\Enums\ProductType;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Classic Burger', 'Chicken Wrap', 'Fries', 'Iced Latte', 'Karak Tea', 'Brownie']);

        return [
            'restaurant_id' => Restaurant::factory(),
            'type' => ProductType::Item,
            'name' => ['en' => $name, 'ar' => $name],
            'price_minor' => fake()->numberBetween(5, 40) * 100,
            'is_available' => true,
        ];
    }

    public function combo(): static
    {
        return $this->state(['type' => ProductType::Combo]);
    }
}
