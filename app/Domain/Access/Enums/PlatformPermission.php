<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

use App\Domain\Access\Contracts\Permission;
use App\Domain\Access\Enums\Concerns\DescribesPermission;

/**
 * Abilities of platform staff (the team that runs the SaaS). The super admin
 * implicitly holds every one of them.
 */
enum PlatformPermission: string implements Permission
{
    use DescribesPermission;

    case ViewClients = 'clients.view';
    case ManageClients = 'clients.manage';
    case ImpersonateOwners = 'clients.impersonate';
    case ManageBilling = 'billing.manage';
    case ManageLeads = 'leads.manage';
    case ManagePlatformStaff = 'staff.manage';
    case ManageWebsite = 'website.manage';
    case ManageSettings = 'settings.manage';
    case ViewSecurity = 'security.view';
    case ViewAuditLog = 'audit.view';
    case ViewReports = 'reports.view';

    public function scope(): string
    {
        return 'platform';
    }

    public function group(): string
    {
        return match ($this) {
            self::ViewClients, self::ManageClients, self::ImpersonateOwners => 'clients',
            self::ManageBilling => 'billing',
            self::ManageLeads, self::ManageWebsite => 'growth',
            self::ManagePlatformStaff, self::ManageSettings => 'platform',
            self::ViewSecurity, self::ViewAuditLog => 'security',
            self::ViewReports => 'reports',
        };
    }
}
