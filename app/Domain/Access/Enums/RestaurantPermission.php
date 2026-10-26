<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

use App\Domain\Access\Contracts\Permission;
use App\Domain\Access\Enums\Concerns\DescribesPermission;

/**
 * Abilities inside one restaurant. Positions are built from these, and the
 * restaurant owner implicitly holds every one of them.
 */
enum RestaurantPermission: string implements Permission
{
    use DescribesPermission;

    case ManageSettings = 'settings.manage';
    case ManageBilling = 'billing.manage';
    case ManageIntegrations = 'integrations.manage';
    case ManageBranches = 'branches.manage';
    case OperateBranch = 'branches.operate';
    case ManageStaff = 'staff.manage';
    case ManageMenu = 'menu.manage';
    case ManageAvailability = 'menu.availability';
    case ViewOrders = 'orders.view';
    case OperateOrders = 'orders.operate';
    case OverrideOrders = 'orders.override';
    case OperateKitchen = 'kitchen.operate';
    case OperatePickup = 'pickup.operate';
    case OperatePager = 'pager.operate';
    case CollectPayments = 'payments.collect';
    case RefundPayments = 'payments.refund';
    case ViewCustomers = 'customers.view';
    case ViewCustomerContacts = 'customers.contacts';
    case ManageLoyalty = 'loyalty.manage';
    case ManagePromotions = 'promotions.manage';
    case ManageCampaigns = 'campaigns.manage';
    case ViewReports = 'reports.view';
    case ViewFinance = 'reports.finance';
    case ExportReports = 'reports.export';
    case ViewAuditLog = 'audit.view';

    public function scope(): string
    {
        return 'restaurant';
    }

    public function group(): string
    {
        return match ($this) {
            self::ManageSettings, self::ManageBilling, self::ManageIntegrations => 'restaurant',
            self::ManageBranches, self::OperateBranch => 'branches',
            self::ManageStaff => 'team',
            self::ManageMenu, self::ManageAvailability => 'menu',
            self::ViewOrders, self::OperateOrders, self::OverrideOrders,
            self::OperateKitchen, self::OperatePickup, self::OperatePager => 'operations',
            self::CollectPayments, self::RefundPayments => 'payments',
            self::ViewCustomers, self::ViewCustomerContacts => 'customers',
            self::ManageLoyalty, self::ManagePromotions, self::ManageCampaigns => 'growth',
            self::ViewReports, self::ViewFinance, self::ExportReports, self::ViewAuditLog => 'reports',
        };
    }
}
