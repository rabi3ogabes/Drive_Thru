<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Domain\Access\Enums\PlatformPermission;
use App\Models\User;

trait ChecksPlatformPermission
{
    private function allowed(User $user, PlatformPermission $permission): bool
    {
        return $user->hasPlatformPermission($permission);
    }
}
