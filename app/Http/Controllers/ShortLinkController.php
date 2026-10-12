<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Ordering\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** /o/7KQ2MX → the order's live status page at its restaurant. */
final class ShortLinkController
{
    public function __invoke(string $code): RedirectResponse
    {
        $order = $this->find($code);

        return redirect()->route('customer.order', ['restaurant' => $order->restaurant->slug, 'order' => $order->short_code]);
    }

    /**
     * A signed link staff copy from the live board: it lets this browser
     * follow that one order live (progress only, no pickup code or actions).
     */
    public function staff(Request $request, string $code): RedirectResponse
    {
        $order = $this->find($code);
        $granted = (array) $request->session()->get(RealtimeAuthController::STAFF_VIEW_SESSION, []);
        $request->session()->put(RealtimeAuthController::STAFF_VIEW_SESSION, array_values(array_slice(array_unique([...$granted, $order->id]), -50)));

        return redirect()->route('customer.order', ['restaurant' => $order->restaurant->slug, 'order' => $order->short_code]);
    }

    private function find(string $code): Order
    {
        return Order::withoutTenancy()->with('restaurant')->where('short_code', strtoupper($code))->firstOrFail();
    }
}
