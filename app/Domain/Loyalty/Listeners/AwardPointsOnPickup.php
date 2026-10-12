<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Listeners;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Loyalty\EarnRules;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\Tiers;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderAdjustment;
use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Points are earned when the guest drives off with the order, never before.
 * Each line earns on its share of what was actually paid (discounts spread
 * in proportion), times its earn boost and the guest's tier, plus any bonus
 * points from promotions. Once per order, however often the event fires.
 */
final class AwardPointsOnPickup
{
    public function __construct(
        private readonly Points $points,
        private readonly EarnRules $rules,
        private readonly Tiers $tiers,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::PickedUp) {
            return;
        }

        DB::transaction(function () use ($event): void {
            $order = Order::withoutTenancy()->with(['items.product', 'customer', 'restaurant'])->whereKey($event->order->id)->lockForUpdate()->first();

            if ($order === null || $order->customer === null || ! $order->restaurant->hasFeature(PlanFeature::Loyalty)) {
                return;
            }

            $program = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $order->restaurant_id)->first();
            $already = LedgerEntry::withoutTenancy()->where('order_id', $order->id)->where('type', LedgerType::Earn->value)->exists();

            if ($program === null || ! $program->isActive() || $already) {
                return;
            }

            $earned = $this->earn($order, $program);

            if ($earned > 0) {
                $this->points->credit($order->customer, LedgerType::Earn, $earned, $order, null);
                $order->forceFill(['points_earned' => $earned])->save();
                $this->tiers->recompute($order->customer);
            }
        });
    }

    private function earn(Order $order, LoyaltyProgram $program): int
    {
        $rules = $this->rules->forRestaurant($order->restaurant_id);
        $tier = $order->customer->tier_id === null ? 10_000 : (int) (Tier::withoutTenancy()->whereKey($order->customer->tier_id)->value('earn_multiplier_bp') ?? 10_000);
        $subtotal = max(1, $order->subtotal_minor);
        $points = 0;

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $share = $item->line_total_minor - intdiv($order->discount_minor * $item->line_total_minor, $subtotal);
            $boost = $this->rules->multiplier($rules, $item->product_id, $item->product?->menu_category_id, $order->branch_id, $order->placed_at ?? now());
            $points += $program->pointsFor($share, $order->currency, intdiv($boost * $tier, 10_000));
        }

        return $points + (int) OrderAdjustment::withoutTenancy()->where('order_id', $order->id)->sum('bonus_points');
    }
}
