<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Branches\Models\Device;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Operations\Operator;
use App\Domain\Printing\TicketRenderer;
use App\Http\Middleware\AuthenticateDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * An 80 mm kitchen ticket or receipt for the browser's print dialog, for a
 * paired screen of the order's branch or staff who work that branch.
 */
final class PrintController
{
    public function __invoke(Request $request, string $code, string $kind, TicketRenderer $tickets): Response
    {
        $order = Order::withoutTenancy()->where('short_code', strtoupper($code))->firstOrFail();
        $operator = $this->operator($request, $order);

        abort_unless($operator !== null && $operator->covers($order->branch_id) && $operator->can(P::OperateOrders, P::OperateKitchen, P::OperatePickup, P::ViewOrders), 403);

        return response($tickets->html($order, $kind))->header('Cache-Control', 'no-store');
    }

    private function operator(Request $request, Order $order): ?Operator
    {
        $device = AuthenticateDevice::resolve($request);

        if ($device instanceof Device && $device->branch_id === $order->branch_id) {
            return Operator::device($device);
        }

        $user = $request->user();

        return $user instanceof User && $user->is_active ? Operator::staff($user, $order->restaurant_id) : null;
    }
}
