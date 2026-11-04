<?php

declare(strict_types=1);

namespace App\Domain\Feedback\Jobs;

use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Notifications\Push\WebPushSender;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * "How was your order?" on the guest's phone, 30 minutes after pickup:
 * only to guests who allowed notifications and haven't rated yet. No
 * WhatsApp or SMS, so asking costs nothing.
 */
final class SendRatingReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $orderId) {}

    public function handle(WebPushSender $push): void
    {
        $order = Order::withoutTenancy()->with(['customer', 'restaurant'])->find($this->orderId);
        $customer = $order?->customer;

        if ($order === null || $customer === null || $order->status !== OrderStatus::PickedUp
            || ! $push->available() || ! $customer->wants('order_updates')
            || OrderRating::withoutTenancy()->where('order_id', $order->id)->exists()) {
            return;
        }

        $locale = in_array($order->locale, ['ar', 'en'], true) ? $order->locale : 'ar';

        $push->send($customer, [
            'title' => __('feedback.push.title', [], $locale),
            'body' => __('feedback.push.body', ['restaurant' => $order->restaurant->getTranslation('name', $locale)], $locale),
            'url' => route('customer.order', ['restaurant' => $order->restaurant->slug, 'order' => $order->short_code]).'#rate',
            'tag' => 'rate-'.$order->id,
        ], $order->restaurant_id);
    }
}
