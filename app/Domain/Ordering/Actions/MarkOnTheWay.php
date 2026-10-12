<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Events\OrderUpdated;
use App\Domain\Ordering\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * "I'm on the way". Recorded on any open order (the kitchen sees the badge);
 * an order held for this signal goes to the kitchen now with a fresh ready
 * time. Repeated taps change nothing.
 */
final class MarkOnTheWay
{
    public function __construct(private readonly TransitionOrder $transition) {}

    public function handle(Order $order): Order
    {
        if ($order->status->isFinal() || $order->status === OrderStatus::PendingPayment || $order->on_the_way_at !== null) {
            return $order;
        }

        return DB::transaction(function () use ($order): Order {
            $now = CarbonImmutable::now();
            $updated = Order::withoutTenancy()->whereKey($order->id)->whereNull('on_the_way_at')->update(['on_the_way_at' => $now]);

            if ($updated === 0) {
                return $order->refresh();
            }

            $order->refresh();
            activity()->performedOn($order)->event('order.on_the_way')->log('order.on_the_way');

            if ($order->status === OrderStatus::Scheduled && $order->timing === OrderTiming::OnTheWay) {
                return $this->transition->handle($order, OrderStatus::Received, TransitionOrder::CUSTOMER, $order->customer_id, 'on_the_way');
            }

            DB::afterCommit(fn () => OrderUpdated::dispatch($order, OrderUpdated::ON_THE_WAY));

            return $order;
        });
    }
}
