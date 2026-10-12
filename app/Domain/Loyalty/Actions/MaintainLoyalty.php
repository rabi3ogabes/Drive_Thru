<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\Tiers;

/**
 * The nightly tidy-up: points past their expiry leave the balance (the
 * soonest-expiring lots are spent first, so those are exactly the expired
 * ones), wallet rewards past their date expire, and tiers follow the points
 * that are still inside the window. Running it twice changes nothing more.
 */
final class MaintainLoyalty
{
    public function __construct(
        private readonly Points $points,
        private readonly Tiers $tiers,
    ) {}

    /**
     * @return array{points: int, rewards: int, tiers: int}
     */
    public function handle(): array
    {
        $expiredPoints = 0;

        LedgerEntry::withoutTenancy()
            ->where('remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->selectRaw('customer_id, sum(remaining) as due')
            ->groupBy('customer_id')
            ->get()
            ->each(function (LedgerEntry $row) use (&$expiredPoints): void {
                $customer = Customer::withoutTenancy()->find($row->customer_id);
                $due = (int) $row->getAttribute('due');

                if ($customer !== null && $due > 0) {
                    $this->points->debit($customer, LedgerType::Expire, min($due, $customer->points_balance), null, null);
                    $expiredPoints += $due;
                }
            });

        $rewards = WalletReward::withoutTenancy()
            ->where('status', WalletReward::AVAILABLE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => WalletReward::EXPIRED, 'updated_at' => now()]);

        $window = (int) (LoyaltyProgram::withoutTenancy()->max('tier_window_months') ?? 12);
        $active = LedgerEntry::withoutTenancy()->where('created_at', '>=', now()->subMonths($window + 1))->distinct()->pluck('customer_id');
        $tiers = 0;

        Customer::withoutTenancy()
            ->where(fn ($query) => $query->whereNotNull('tier_id')->orWhereIn('id', $active))
            ->lazyById(500)
            ->each(function (Customer $customer) use (&$tiers): void {
                $before = $customer->tier_id;
                $after = $this->tiers->recompute($customer)?->id;
                $tiers += $before === $after ? 0 : 1;
            });

        return ['points' => $expiredPoints, 'rewards' => $rewards, 'tiers' => $tiers];
    }
}
