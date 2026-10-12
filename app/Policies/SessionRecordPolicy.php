<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Security\Models\SessionRecord;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

final class SessionRecordPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ViewSecurity);
    }

    /** Revoking someone else's session; your own are managed from "Devices & sessions". */
    public function delete(User $user, SessionRecord $session): bool
    {
        return $this->allowed($user, PlatformPermission::ViewSecurity) && $session->user_id !== $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SessionRecord $session): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
