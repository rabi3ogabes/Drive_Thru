<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Billing\Models\Plan;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

final class PlanPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling);
    }

    /** A plan that ever had subscribers is archived (deactivated), never deleted. */
    public function delete(User $user, Plan $plan): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling) && ! $plan->subscriptions()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
