<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customers\CustomerAuth;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\View\View;

/** A printable bilingual receipt, for the guest who placed the order. */
final class ReceiptController
{
    public function __invoke(Restaurant $restaurant, Order $order, CustomerAuth $auth): View
    {
        abort_unless($order->restaurant_id === $restaurant->id, 404);

        $customer = $auth->current($restaurant);
        abort_unless($customer !== null && $customer->id === $order->customer_id, 403);

        $order->load(['items', 'branch', 'payment', 'adjustments']);

        return view('customer.receipt', ['restaurant' => $restaurant, 'order' => $order]);
    }
}
