<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Listeners;

use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\Tiers;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Events\PaymentRefunded;
use App\Domain\Payments\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Money given back takes back the points it earned, in proportion: half
 * refunded, half the points. Never more than were earned on the order, and
 * never more than the guest still has (the shortfall is noted).
 */
final class ReversePointsOnRefund
{
    public function __construct(
        private readonly Points $points,
        private readonly Tiers $tiers,
    ) {}

    public function handle(PaymentRefunded $event): void
    {
        $refund = $event->refund;

        if ($refund->status !== Refund::SUCCEEDED) {
            return;
        }

        DB::transaction(function () use ($refund): void {
            $payment = Payment::withoutTenancy()->find($refund->payment_id);
            $order = $payment === null ? null : Order::withoutTenancy()->with('customer')->whereKey($payment->order_id)->lockForUpdate()->first();

            if ($payment === null || $order === null || $order->customer === null || $order->points_earned <= 0) {
                return;
            }

            $reversed = -1 * (int) LedgerEntry::withoutTenancy()->where('order_id', $order->id)->where('type', LedgerType::Reverse->value)->sum('points');
            $fullyRefunded = $payment->fresh()?->refundableMinor() === 0;
            $due = $fullyRefunded
                ? $order->points_earned - $reversed
                : min($order->points_earned - $reversed, (int) round($order->points_earned * $refund->amount_minor / max(1, $payment->amount_minor)));
            $take = min($due, $order->customer->fresh()->points_balance ?? 0);

            if ($take > 0) {
                $this->points->debit($order->customer, LedgerType::Reverse, $take, $order, $take < $due ? 'short:'.($due - $take) : null);
                $this->tiers->recompute($order->customer);
            }
        });
    }
}
