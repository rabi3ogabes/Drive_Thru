<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Push\WebPushSender;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/** "Your order is ready" on the guest's phone, for guests who allowed notifications on the order page. */
final class PushOrderReadyToGuest implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private readonly WebPushSender $push) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Ready || ! $this->push->available()) {
            return;
        }

        $order = Order::withoutTenancy()->with(['customer', 'branch'])->find($event->order->id);
        $customer = $order?->customer;

        if ($order === null || $customer === null || ! $customer->wants('order_updates')) {
            return;
        }

        $locale = in_array($order->locale, ['ar', 'en'], true) ? $order->locale : 'ar';
        $number = $order->displayNumber();
        $branch = $order->branch->getTranslation('name', $locale);

        $this->push->send($customer, [
            'title' => __('notifications.push.ready_title', ['number' => $number], $locale),
            'body' => __('notifications.push.ready_body', ['branch' => $branch, 'code' => (string) $order->pickup_code], $locale),
            'url' => route('short.order', $order->short_code),
            'tag' => 'order-'.$order->id,
        ], $order->restaurant_id);
    }
}
