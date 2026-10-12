<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Domain\Access\Enums\RestaurantPermission as P;

/**
 * The positions every new restaurant starts with. Owners can edit them or
 * build custom ones; the "key" keeps system positions identifiable.
 */
final class DefaultPositions
{
    /**
     * @return array<string, list<P>>
     */
    public static function all(): array
    {
        return [
            'general_manager' => array_values(array_filter(
                P::cases(),
                fn (P $p): bool => ! in_array($p, [P::ManageBilling, P::ManageSettings, P::ManageIntegrations], true),
            )),
            'branch_manager' => [
                P::OperateBranch, P::ManageStaff, P::ManageAvailability, P::ViewOrders, P::OperateOrders,
                P::OperateKitchen, P::OperatePickup, P::CollectPayments, P::RefundPayments,
                P::ViewCustomers, P::ViewReports, P::ExportReports,
            ],
            'cashier' => [P::ViewOrders, P::OperateOrders, P::OperatePickup, P::CollectPayments],
            'kitchen' => [P::ViewOrders, P::OperateKitchen],
            'expeditor' => [P::ViewOrders, P::OperatePickup],
            'accountant' => [P::ViewOrders, P::ViewReports, P::ViewFinance, P::ExportReports],
            'marketing' => [
                P::ViewCustomers, P::ViewCustomerContacts, P::ManageLoyalty, P::ManagePromotions,
                P::ManageCampaigns, P::ViewReports,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
