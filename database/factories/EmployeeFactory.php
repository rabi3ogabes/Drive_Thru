<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'is_owner' => false,
            'status' => EmployeeStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(['is_owner' => true]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => EmployeeStatus::Suspended]);
    }
}
