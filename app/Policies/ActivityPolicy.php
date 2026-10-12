<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log is read-only for everyone.
 */
final class ActivityPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ViewAuditLog);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->allowed($user, PlatformPermission::ViewAuditLog);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
