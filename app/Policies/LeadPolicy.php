<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

final class LeadPolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageLeads);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->allowed($user, PlatformPermission::ManageLeads);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageLeads);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->allowed($user, PlatformPermission::ManageLeads);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $lead->status !== LeadStatus::Won
            && $this->allowed($user, PlatformPermission::ManageLeads)
            && $this->allowed($user, PlatformPermission::ManageClients);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $lead->status !== LeadStatus::Won && $this->allowed($user, PlatformPermission::ManageLeads);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageLeads);
    }
}
