<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customers\CustomerAuth;
use App\Domain\Ordering\Models\Order;
use App\Domain\ReadyCall\Models\PagerTicket;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * Realtime sign-off for clients that are not panel users:
 *
 * - a paired screen may listen to its own branch's orders only;
 * - a guest may listen to their own order (or one they opened through a
 *   signed staff link, which grants that order for the browser session);
 * - whoever holds a live pager link may listen to that ticket only.
 *
 * Panel staff use Laravel's /broadcasting/auth with routes/channels.php.
 */
final class RealtimeAuthController
{
    public const STAFF_VIEW_SESSION = 'realtime.orders';

    public function __invoke(Request $request, CustomerAuth $auth): mixed
    {
        $channel = (string) $request->input('channel_name', '');
        $socket = (string) $request->input('socket_id', '');

        abort_unless(preg_match('/^\d+\.\d+$/', $socket) === 1 && str_starts_with($channel, 'private-'), Response::HTTP_FORBIDDEN);

        $name = substr($channel, strlen('private-'));

        abort_unless($this->allowed($request, $auth, $name), Response::HTTP_FORBIDDEN);

        return Broadcast::driver()->validAuthenticationResponse($request, true) ?? response()->noContent(200);
    }

    private function allowed(Request $request, CustomerAuth $auth, string $name): bool
    {
        if (preg_match('/^branch\.(\d+)\.orders$/', $name, $m) === 1) {
            $device = AuthenticateDevice::resolve($request);

            return $device !== null && $device->branch_id === (int) $m[1];
        }

        if (preg_match('/^branch\.(\d+)\.pager$/', $name, $m) === 1) {
            $device = AuthenticateDevice::resolve($request);

            return $device !== null && $device->branch_id === (int) $m[1];
        }

        // The pager link is the key: whoever holds it may hear that one ticket.
        if (preg_match('/^pager\.([A-Za-z0-9]{32})$/', $name, $m) === 1) {
            return PagerTicket::findLive($m[1])?->isLive() ?? false;
        }

        if (preg_match('/^order\.([2-9A-HJ-NP-Z]{6})$/', $name, $m) === 1) {
            $order = Order::withoutTenancy()->with('restaurant')->where('short_code', $m[1])->first();

            if ($order === null) {
                return false;
            }

            $granted = (array) $request->session()->get(self::STAFF_VIEW_SESSION, []);

            return in_array($order->id, $granted, true) || $auth->current($order->restaurant)?->id === $order->customer_id;
        }

        return false;
    }
}
