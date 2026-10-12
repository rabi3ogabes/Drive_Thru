<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Exceptions\NotEnoughPoints;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Points;
use App\Domain\Ordering\Data\Adjustment;
use App\Domain\Ordering\Data\CheckoutBenefits;
use App\Domain\Ordering\Data\PricedCart;
use App\Domain\Ordering\Exceptions\OrderRejected;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderAdjustment;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\Redemption;

/**
 * Inside PlaceOrder's transaction: makes the discounts on the priced cart
 * real. Every limited thing is locked and checked again (promotion and
 * coupon uses, the wallet reward, the points), so two orders racing for the
 * last use or the same points can't both win: the loser is rejected and
 * its whole order rolls back.
 */
final class RecordBenefits
{
    public function __construct(private readonly Points $points) {}

    /**
     * @throws OrderRejected
     */
    public function handle(Order $order, Customer $customer, PricedCart $cart, ?CheckoutBenefits $asked): void
    {
        // What the guest chose must still be what the cart gives, or the total they saw is gone.
        $askedCoupon = $asked?->couponCode !== null && trim($asked->couponCode) !== '';
        $kinds = array_map(fn (Adjustment $a): string => $a->kind, $cart->adjustments);

        if (($askedCoupon && ! in_array(Adjustment::COUPON, $kinds, true))
            || ($asked?->walletRewardId !== null && ! in_array(Adjustment::REWARD, $kinds, true))
            || ($asked !== null && $asked->pointsToRedeem !== $cart->pointsRedeemed)) {
            throw OrderRejected::because('benefit_changed');
        }

        $coupon = $askedCoupon ? Coupon::findCode($order->restaurant_id, (string) $asked?->couponCode) : null;

        foreach ($cart->adjustments as $adjustment) {
            match ($adjustment->kind) {
                Adjustment::PROMOTION, Adjustment::COUPON => $this->promotion($order, $customer, $adjustment, $adjustment->kind === Adjustment::COUPON ? $coupon : null),
                Adjustment::REWARD => $this->reward($order, $customer, $adjustment),
                default => null,
            };

            OrderAdjustment::withoutTenancy()->create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'kind' => $adjustment->kind,
                'source_id' => $adjustment->sourceId,
                'label' => $adjustment->label,
                'amount_minor' => $adjustment->amountMinor,
                'bonus_points' => $adjustment->bonusPoints,
                'created_at' => now(),
            ]);
        }

        if ($cart->pointsRedeemed > 0) {
            try {
                $this->points->debit($customer, LedgerType::Redeem, $cart->pointsRedeemed, $order, null);
            } catch (NotEnoughPoints) {
                throw OrderRejected::because('benefit_changed');
            }
        }
    }

    private function promotion(Order $order, Customer $customer, Adjustment $adjustment, ?Coupon $coupon): void
    {
        $promotion = Promotion::withoutTenancy()->whereKey($adjustment->sourceId)->lockForUpdate()->first();

        if ($promotion === null || $promotion->archived_at !== null
            || ($promotion->usage_limit !== null && $promotion->used_count >= $promotion->usage_limit)
            || ($promotion->per_customer_limit !== null && $this->uses($promotion->id, $customer->id) >= $promotion->per_customer_limit)) {
            throw OrderRejected::because('benefit_changed');
        }

        if ($coupon !== null) {
            $coupon = Coupon::withoutTenancy()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();

            if (($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit)
                || ($coupon->per_customer_limit !== null && $this->uses($promotion->id, $customer->id, $coupon->id) >= $coupon->per_customer_limit)) {
                throw OrderRejected::because('benefit_changed');
            }

            $coupon->increment('used_count');
        }

        $promotion->increment('used_count');

        Redemption::withoutTenancy()->create([
            'restaurant_id' => $order->restaurant_id,
            'promotion_id' => $promotion->id,
            'coupon_id' => $coupon?->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'discount_minor' => $adjustment->amountMinor,
        ]);
    }

    private function reward(Order $order, Customer $customer, Adjustment $adjustment): void
    {
        $wallet = WalletReward::withoutTenancy()->whereKey($adjustment->sourceId)->where('customer_id', $customer->id)->lockForUpdate()->first();

        if ($wallet === null || ! $wallet->isUsable()) {
            throw OrderRejected::because('benefit_changed');
        }

        $wallet->forceFill(['status' => WalletReward::USED, 'used_order_id' => $order->id])->save();
    }

    private function uses(int $promotionId, int $customerId, ?int $couponId = null): int
    {
        return Redemption::withoutTenancy()
            ->where('promotion_id', $promotionId)
            ->where('customer_id', $customerId)
            ->when($couponId !== null, fn ($query) => $query->where('coupon_id', $couponId))
            ->whereNull('released_at')
            ->count();
    }
}
