<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Api;

use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderAdjustment;
use App\Domain\Ordering\Models\OrderItem;

/**
 * The one shape an order has outside the app: in the REST API and in
 * webhooks, so a POS reads both the same way. Money is in minor units with
 * the currency; names are bilingual; times are ISO 8601 UTC. The guest's
 * phone is left out: integrations see the order, not the person.
 */
final class OrderPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Order $order): array
    {
        $order->loadMissing(['branch', 'items', 'lane', 'customer', 'adjustments']);

        return [
            'id' => $order->id,
            'number' => $order->displayNumber(),
            'code' => $order->short_code,
            'status' => $order->status->value,
            'payment' => [
                'status' => $order->payment_status->value,
                'method' => $order->payment_method->value,
            ],
            'currency' => $order->currency,
            'totals' => [
                'subtotal_minor' => $order->subtotal_minor,
                'discount_minor' => $order->discount_minor,
                'tax_minor' => $order->tax_minor,
                'service_fee_minor' => $order->service_fee_minor,
                'total_minor' => $order->total_minor,
            ],
            'timing' => $order->timing->value,
            'branch' => [
                'id' => $order->branch->id,
                'code' => $order->branch->code,
                'name' => $order->branch->getTranslations('name'),
            ],
            'customer' => ['name' => $order->customer?->name],
            'vehicle' => $order->vehicle,
            'lane' => $order->lane?->name,
            'notes' => $order->notes,
            'locale' => $order->locale,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'options_minor' => $item->options_minor,
                'total_minor' => $item->line_total_minor,
                'options' => array_map(fn (array $option): array => [
                    'group' => $option['group'],
                    'option' => $option['option'],
                    'price_minor' => $option['price_minor'],
                ], $item->options ?? []),
                'notes' => $item->notes,
                'station' => $item->station?->value,
            ])->values()->all(),
            'times' => [
                'placed_at' => $order->placed_at?->toIso8601ZuluString(),
                'scheduled_for' => $order->scheduled_for?->toIso8601ZuluString(),
                'ready_estimate_at' => $order->ready_estimate_at?->toIso8601ZuluString(),
                'received_at' => $order->received_at?->toIso8601ZuluString(),
                'ready_at' => $order->ready_at?->toIso8601ZuluString(),
                'picked_up_at' => $order->picked_up_at?->toIso8601ZuluString(),
                'cancelled_at' => $order->cancelled_at?->toIso8601ZuluString(),
                'arrived_at' => $order->arrived_at?->toIso8601ZuluString(),
            ],
            'adjustments' => $order->adjustments->map(fn (OrderAdjustment $a): array => [
                'kind' => $a->kind,
                'label' => $a->label,
                'amount_minor' => $a->amount_minor,
                'bonus_points' => $a->bonus_points,
            ])->values()->all(),
            'points' => ['redeemed' => $order->points_redeemed, 'earned' => $order->points_earned],
            'cancel_reason' => $order->cancel_reason,
        ];
    }
}
