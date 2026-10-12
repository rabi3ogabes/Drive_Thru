<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Listeners;

use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\Redemption;
use Illuminate\Support\Facades\DB;

/**
 * A cancelled order gives everything back: the points the guest spent (as a
 * fresh lot), the wallet reward (unless it has expired meanwhile) and the
 * promotion and coupon uses. Safe to run twice.
 */
final class ReturnBenefitsOnCancel
{
    public function __construct(private readonly Points $points) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Cancelled) {
            return;
        }

        DB::transaction(function () use ($event): void {
            $order = Order::withoutTenancy()->with('customer')->whereKey($event->order->id)->lockForUpdate()->first();

            if ($order === null || $order->customer === null) {
                return;
            }

            $returned = LedgerEntry::withoutTenancy()->where('order_id', $order->id)->where('type', LedgerType::Returned->value)->exists();

            if ($order->points_redeemed > 0 && ! $returned) {
                $this->points->credit($order->customer, LedgerType::Returned, $order->points_redeemed, $order, null);
            }

            WalletReward::withoutTenancy()->where('used_order_id', $order->id)->lockForUpdate()->get()->each(function (WalletReward $wallet): void {
                $expired = $wallet->expires_at !== null && $wallet->expires_at->isPast();
                $wallet->forceFill(['status' => $expired ? WalletReward::EXPIRED : WalletReward::AVAILABLE, 'used_order_id' => null])->save();
            });

            Redemption::withoutTenancy()->where('order_id', $order->id)->whereNull('released_at')->lockForUpdate()->get()->each(function (Redemption $redemption): void {
                $redemption->forceFill(['released_at' => now()])->save();
                Promotion::withoutTenancy()->whereKey($redemption->promotion_id)->where('used_count', '>', 0)->decrement('used_count');

                if ($redemption->coupon_id !== null) {
                    Coupon::withoutTenancy()->whereKey($redemption->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
                }
            });
        });
    }
}
