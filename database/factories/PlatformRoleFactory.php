<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformRole>
 */
class PlatformRoleFactory extends Factory
{
    protected $model = PlatformRole::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => 'Support', 'ar' => 'الدعم'],
            'permissions' => [PlatformPermission::ViewClients],
            'is_system' => false,
        ];
    }

    /**
     * @param  list<PlatformPermission>  $permissions
     */
    public function granting(array $permissions): static
    {
        return $this->state(['permissions' => $permissions]);
    }
}
