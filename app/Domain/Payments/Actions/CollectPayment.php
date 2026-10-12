<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Events\OrderUpdated;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Money taken at the window for a pay-at-pickup order, in cash or by card on
 * the branch terminal. Recorded as a paid counter payment; safe to repeat.
 */
final class CollectPayment
{
    public const TENDERS = ['cash', 'card'];

    public function handle(Order $order, string $tender, string $actorType, int $actorId): Order
    {
        if (! in_array($tender, self::TENDERS, true)) {
            throw new InvalidArgumentException('Unknown tender.');
        }

        return DB::transaction(function () use ($order, $tender, $actorType, $actorId): Order {
            $order = Order::withoutTenancy()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === PaymentStatus::Paid || $order->payment_method !== PaymentMethod::PayAtPickup || $order->status === OrderStatus::Cancelled) {
                return $order;
            }

            Payment::withoutTenancy()->create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'method' => PaymentMethod::PayAtPickup,
                'gateway' => Payment::COUNTER,
                'status' => PaymentStatus::Paid,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'meta' => ['tender' => $tender, 'by' => $actorType.':'.$actorId],
                'paid_at' => now(),
            ]);

            $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();
            activity()->performedOn($order)->event('payment.collected')->withProperties(['tender' => $tender, 'amount_minor' => $order->total_minor, 'by' => $actorType.':'.$actorId])->log('payment.collected');

            DB::afterCommit(fn () => OrderUpdated::dispatch($order, OrderUpdated::PAID));

            return $order;
        });
    }
}
