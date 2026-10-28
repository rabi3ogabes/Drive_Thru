<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Listeners;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A picked-up order counts for the campaign that brought it (last touch):
 * the campaign whose link the guest ordered through, otherwise the latest
 * campaign message they received in the 7 days before. Each order counts
 * once, and each message brings at most one order.
 */
final class AttributeCampaignOrder
{
    public const DAYS = 7;

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if ($event->to !== OrderStatus::PickedUp || $order->customer_id === null) {
            return;
        }

        if (CampaignMessage::withoutTenancy()->where('order_id', $order->id)->exists()) {
            return;
        }

        $message = $this->byLink($order) ?? $this->byTime($order);

        if ($message === null) {
            return;
        }

        DB::transaction(function () use ($message, $order): void {
            $claimed = CampaignMessage::withoutTenancy()->whereKey($message->id)->whereNull('order_id')->update([
                'order_id' => $order->id,
                'revenue_minor' => $order->total_minor,
                'attributed_at' => now(),
                'updated_at' => now(),
            ]);

            if ($claimed === 1) {
                Campaign::withoutTenancy()->whereKey($message->campaign_id)->incrementEach([
                    'orders_count' => 1,
                    'revenue_minor' => $order->total_minor,
                ]);
            }
        });
    }

    private function byLink(Order $order): ?CampaignMessage
    {
        $code = $order->source['campaign'] ?? null;

        if (! is_string($code) || $code === '') {
            return null;
        }

        $campaignId = Campaign::withoutTenancy()->where('restaurant_id', $order->restaurant_id)->where('link_code', $code)->value('id');

        return $campaignId === null ? null : $this->sentTo($order)->where('campaign_id', $campaignId)->first();
    }

    private function byTime(Order $order): ?CampaignMessage
    {
        $at = $order->picked_up_at ?? now();

        return $this->sentTo($order)
            ->where('sent_at', '>=', $at->subDays(self::DAYS))
            ->where('sent_at', '<=', $at)
            ->latest('sent_at')
            ->first();
    }

    /**
     * @return Builder<CampaignMessage>
     */
    private function sentTo(Order $order): Builder
    {
        return CampaignMessage::withoutTenancy()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('customer_id', $order->customer_id)
            ->where('status', CampaignMessage::SENT)
            ->whereNull('order_id');
    }
}
