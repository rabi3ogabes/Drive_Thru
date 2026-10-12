<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\GatewayResolver;
use App\Domain\Payments\Sadad\SadadGateway;
use App\Domain\Payments\Sadad\SadadNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SADAD Web Checkout: the page that hands the guest to SADAD with a signed
 * form, the browser callback after paying, and the server webhook.
 */
final class SadadController
{
    /** A short branded page that posts the signed form to SADAD at once. */
    public function checkout(string $reference, GatewayResolver $gateways): View|RedirectResponse
    {
        $payment = Payment::withoutTenancy()->with('order.restaurant')->where('reference', $reference)->where('gateway', SadadGateway::NAME)->firstOrFail();
        $order = $payment->order;
        $gateway = $gateways->for($payment->restaurant_id);

        if ($payment->status !== PaymentStatus::Pending || ! $gateway instanceof SadadGateway) {
            return redirect()->route('customer.order', [$order->restaurant, $order]);
        }

        return view('payments.sadad-redirect', [
            'restaurant' => $order->restaurant,
            'order' => $order,
            'action' => $gateway->checkoutUrl(),
            'fields' => $gateway->checkoutFields($payment),
        ]);
    }

    /** The guest comes back from SADAD (form post). Verified, applied, then shown their order. */
    public function callback(Request $request, SadadNotification $notification): RedirectResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->post();
        $payment = $notification->apply($params, 'callback');

        if ($payment === null) {
            // Not trusted: the webhook or a status check will settle it. Never trust the browser.
            $reference = $request->post('ORDERID');
            $payment = is_string($reference) ? Payment::withoutTenancy()->with('order.restaurant')->where('reference', $reference)->first() : null;
        }

        if ($payment === null) {
            abort(404);
        }

        $order = $payment->order;

        return redirect()->route('customer.order', [$order->restaurant, $order]);
    }

    /** SADAD's server-to-server notification. Always answers 200 as SADAD requires. */
    public function webhook(Request $request, SadadNotification $notification): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $notification->apply($payload, 'webhook');

        return response()->json(['status' => 'success']);
    }
}
