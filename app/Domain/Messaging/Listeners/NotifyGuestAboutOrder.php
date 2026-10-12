<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Listeners;

use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Tells the guest about their order on WhatsApp (SMS as the fallback):
 * received, ready, and the receipt after pickup. Each message goes once per
 * order, only if the guest hasn't turned it off, in the order's language.
 */
final class NotifyGuestAboutOrder implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private readonly Messenger $messenger) {}

    public function handle(OrderStatusChanged $event): void
    {
        $purpose = match ($event->to) {
            OrderStatus::Received => Message::ORDER_RECEIVED,
            OrderStatus::Ready => Message::ORDER_READY,
            OrderStatus::PickedUp => Message::RECEIPT,
            default => null,
        };

        if ($purpose === null) {
            return;
        }

        $order = Order::withoutTenancy()->with(['customer', 'branch.restaurant'])->find($event->order->id);
        $customer = $order?->customer;

        if ($order === null || $customer === null || ! $customer->wants($purpose === Message::RECEIPT ? 'receipts' : 'order_updates')) {
            return;
        }

        $alreadySent = MessageDelivery::withoutTenancy()
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->where('purpose', $purpose)
            ->where('status', '!=', MessageDelivery::FAILED)
            ->exists();

        if ($alreadySent) {
            return;
        }

        $this->messenger->send($this->message($order, $purpose));
    }

    private function message(Order $order, string $purpose): Message
    {
        $locale = in_array($order->locale, ['ar', 'en'], true) ? $order->locale : 'ar';
        $restaurant = $order->branch->restaurant;
        $tz = $restaurant->timezone;
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $number = $order->displayNumber();
            $link = route('short.order', $order->short_code);

            [$parameters, $text] = match ($purpose) {
                Message::ORDER_RECEIVED => [
                    [$restaurant->name, $number, $this->when($order, $tz)],
                    __('notifications.guest.received', ['restaurant' => $restaurant->name, 'number' => $number, 'when' => $this->when($order, $tz), 'link' => $link]),
                ],
                Message::ORDER_READY => [
                    [$number, $order->branch->name],
                    __('notifications.guest.ready', ['number' => $number, 'branch' => $order->branch->name, 'code' => $order->pickup_code]),
                ],
                default => [
                    [$number, Money::format($order->total_minor, $order->currency), $link],
                    __('notifications.guest.receipt', ['restaurant' => $restaurant->name, 'number' => $number, 'total' => Money::format($order->total_minor, $order->currency), 'link' => $link]),
                ],
            };
        } finally {
            app()->setLocale($previous);
        }

        return new Message(
            phone: $order->customer->phone,
            purpose: $purpose,
            text: $text,
            parameters: $parameters,
            locale: $locale,
            restaurantId: $order->restaurant_id,
            subject: $order,
        );
    }

    private function when(Order $order, string $tz): string
    {
        if ($order->scheduled_for !== null) {
            return $order->scheduled_for->setTimezone($tz)->format('H:i');
        }

        return $order->ready_estimate_at?->setTimezone($tz)->format('H:i') ?? '—';
    }
}
