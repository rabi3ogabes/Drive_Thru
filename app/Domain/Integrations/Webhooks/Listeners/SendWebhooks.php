<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Webhooks\Listeners;

use App\Domain\Integrations\Api\OrderPayload;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\Webhooks\DispatchWebhook;
use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Events\PaymentRefunded;
use App\Domain\Payments\Events\PaymentSucceeded;
use App\Domain\Payments\Models\Refund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Turns order and payment events into webhooks. Restaurants without an
 * endpoint cost one indexed query; the payload is built only when needed.
 */
final class SendWebhooks implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private readonly DispatchWebhook $dispatch) {}

    public function handle(OrderStatusChanged|PaymentSucceeded|PaymentRefunded $event): void
    {
        match (true) {
            $event instanceof OrderStatusChanged => $this->order(
                $event->order,
                $event->from === null ? WebhookEvent::OrderCreated : WebhookEvent::OrderStatusChanged,
                $event->from === null ? [] : ['previous_status' => $event->from->value],
            ),
            $event instanceof PaymentSucceeded => $this->payment($event->payment, WebhookEvent::PaymentSucceeded),
            default => $event->refund->status === Refund::SUCCEEDED ? $this->refund($event->refund) : null,
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function order(Order $order, WebhookEvent $event, array $extra): void
    {
        if (! $this->wanted($order->restaurant_id)) {
            return;
        }

        $fresh = Order::withoutTenancy()->find($order->id);

        if ($fresh !== null) {
            $this->dispatch->handle($order->restaurant_id, $event, ['order' => OrderPayload::from($fresh)] + $extra);
        }
    }

    private function payment(Payment $payment, WebhookEvent $event): void
    {
        if (! $this->wanted($payment->restaurant_id)) {
            return;
        }

        $order = Order::withoutTenancy()->find($payment->order_id);

        $this->dispatch->handle($payment->restaurant_id, $event, [
            'payment' => $this->paymentData($payment),
            'order' => $order === null ? null : OrderPayload::from($order),
        ]);
    }

    private function refund(Refund $refund): void
    {
        if (! $this->wanted($refund->restaurant_id)) {
            return;
        }

        $payment = Payment::withoutTenancy()->find($refund->payment_id);

        $this->dispatch->handle($refund->restaurant_id, WebhookEvent::PaymentRefunded, [
            'refund' => [
                'id' => $refund->id,
                'amount_minor' => $refund->amount_minor,
                'reason' => $refund->reason,
                'provider_reference' => $refund->provider_reference,
            ],
            'payment' => $payment === null ? null : $this->paymentData($payment),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentData(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
            'method' => $payment->method->value,
            'gateway' => $payment->gateway,
            'amount_minor' => $payment->amount_minor,
            'refunded_minor' => $payment->refunded_minor,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'paid_at' => $payment->paid_at?->toIso8601ZuluString(),
        ];
    }

    private function wanted(int $restaurantId): bool
    {
        return WebhookEndpoint::withoutTenancy()->where('restaurant_id', $restaurantId)->where('is_active', true)->exists();
    }
}
