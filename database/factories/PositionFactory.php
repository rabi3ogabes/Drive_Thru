<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\Position;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => ['en' => 'Custom position', 'ar' => 'منصب مخصص'],
            'permissions' => [RestaurantPermission::ViewOrders],
            'is_system' => false,
        ];
    }

    /**
     * @param  list<RestaurantPermission>  $permissions
     */
    public function granting(array $permissions): static
    {
        return $this->state(['permissions' => $permissions]);
    }
}
