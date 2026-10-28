<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Exceptions\NotEnoughPoints;
use App\Domain\Loyalty\Points;
use App\Models\User;

/**
 * A goodwill or correction change to a guest's points, by a named staff
 * member with a reason, in the ledger and the audit log.
 */
final class AdjustPoints
{
    public function __construct(private readonly Points $points) {}

    /**
     * @throws NotEnoughPoints when taking away more than the guest has
     */
    public function handle(Customer $customer, int $points, string $reason, User $by): void
    {
        $reason = mb_substr(trim($reason), 0, 150);

        if ($points > 0) {
            $this->points->credit($customer, LedgerType::Adjust, $points, null, $reason, $by);
        } elseif ($points < 0) {
            $this->points->debit($customer, LedgerType::Adjust, -$points, null, $reason, $by);
        } else {
            return;
        }

        activity()->performedOn($customer)->causedBy($by)->event('loyalty.points_adjusted')
            ->withProperties(['points' => $points, 'reason' => $reason, 'restaurant_id' => $customer->restaurant_id])->log('loyalty.points_adjusted');
    }
}
