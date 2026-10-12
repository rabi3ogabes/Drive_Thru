<?php

declare(strict_types=1);

namespace App\Domain\Loyalty;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Exceptions\NotEnoughPoints;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Ordering\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only way points move. Credits become lots (with an expiry from the
 * program); debits spend the soonest-expiring lots first. The customer row
 * is locked for the whole movement, so the balance always equals the sum of
 * the ledger and of the unspent lots, even when two requests race.
 */
final class Points
{
    public function credit(Customer $customer, LedgerType $type, int $points, ?Order $order, ?string $note, ?User $by = null): LedgerEntry
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Credit a positive number of points.');
        }

        return DB::transaction(function () use ($customer, $type, $points, $order, $note, $by): LedgerEntry {
            $locked = $this->lock($customer);
            $months = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $locked->restaurant_id)->value('expiry_months');

            $entry = LedgerEntry::withoutTenancy()->create([
                'restaurant_id' => $locked->restaurant_id,
                'customer_id' => $locked->id,
                'order_id' => $order?->id,
                'type' => $type,
                'points' => $points,
                'remaining' => $points,
                'expires_at' => $months === null ? null : now()->addMonths((int) $months),
                'note' => $note === null ? null : mb_substr($note, 0, 200),
                'created_by' => $by?->id,
                'created_at' => now(),
            ]);

            $this->setBalance($customer, $locked->points_balance + $points);

            return $entry;
        });
    }

    /**
     * @throws NotEnoughPoints
     */
    public function debit(Customer $customer, LedgerType $type, int $points, ?Order $order, ?string $note, ?User $by = null): LedgerEntry
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Debit a positive number of points.');
        }

        return DB::transaction(function () use ($customer, $type, $points, $order, $note, $by): LedgerEntry {
            $locked = $this->lock($customer);

            if ($locked->points_balance < $points) {
                throw new NotEnoughPoints;
            }

            $this->consume($locked, $points);

            $entry = LedgerEntry::withoutTenancy()->create([
                'restaurant_id' => $locked->restaurant_id,
                'customer_id' => $locked->id,
                'order_id' => $order?->id,
                'type' => $type,
                'points' => -$points,
                'remaining' => 0,
                'note' => $note === null ? null : mb_substr($note, 0, 200),
                'created_by' => $by?->id,
                'created_at' => now(),
            ]);

            $this->setBalance($customer, $locked->points_balance - $points);

            return $entry;
        });
    }

    /** Spends the oldest-expiring unspent lots; lots that never expire go last. */
    private function consume(Customer $customer, int $points): void
    {
        $lots = LedgerEntry::withoutTenancy()
            ->where('customer_id', $customer->id)
            ->where('remaining', '>', 0)
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($points === 0) {
                break;
            }

            $take = min($points, $lot->remaining);
            $lot->forceFill(['remaining' => $lot->remaining - $take])->save();
            $points -= $take;
        }
    }

    private function lock(Customer $customer): Customer
    {
        return Customer::withoutTenancy()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
    }

    private function setBalance(Customer $customer, int $balance): void
    {
        Customer::withoutTenancy()->whereKey($customer->id)->update(['points_balance' => $balance]);
        $customer->setAttribute('points_balance', $balance);
        $customer->syncOriginalAttribute('points_balance');
    }
}
