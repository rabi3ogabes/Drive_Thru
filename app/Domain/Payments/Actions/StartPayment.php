<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\GatewayResolver;
use InvalidArgumentException;

/**
 * Starts an online payment for an order awaiting one, with the restaurant's
 * provider. Returns where to send the guest (a provider page), or null when
 * the provider answered at once.
 */
final class StartPayment
{
    public function __construct(
        private readonly GatewayResolver $gateways,
        private readonly CompletePayment $complete,
    ) {}

    public function handle(Order $order, PaymentMethod $method): ?string
    {
        $order->loadMissing('restaurant');
        $gateway = $this->gateways->for($order->restaurant);

        if (! $method->isOnline() || $order->status !== OrderStatus::PendingPayment || $gateway === null || ! in_array($method, $gateway->methods(), true)) {
            throw new InvalidArgumentException('Only orders awaiting an online payment can be charged, with a method the restaurant offers.');
        }

        $payment = Payment::withoutTenancy()->create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'method' => $method,
            'gateway' => $gateway->name(),
            'mode' => $gateway->mode(),
            'status' => PaymentStatus::Pending,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
        ]);

        if ($order->payment_method !== $method) {
            $order->forceFill(['payment_method' => $method])->save();
        }

        $result = $gateway->charge($payment, route('customer.order', ['restaurant' => $order->restaurant->slug, 'order' => $order->short_code]));
        $payment->forceFill(['reference' => $result->reference])->save();

        if ($result->needsRedirect()) {
            $payment->forceFill(['redirect_url' => $result->redirectUrl])->save();

            return $result->redirectUrl;
        }

        $this->complete->handle($payment, $result->isPaid(), $result->failureReason);

        return null;
    }
}
