<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Platform\Models\Announcement;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

final class AnnouncementPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageSettings);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->allowed($user, PlatformPermission::ManageSettings);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageSettings);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->allowed($user, PlatformPermission::ManageSettings);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->allowed($user, PlatformPermission::ManageSettings);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageSettings);
    }
}
