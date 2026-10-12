<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Listeners;

use App\Domain\Ordering\Events\OrderBroadcast;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Events\OrderUpdated;
use Throwable;

/**
 * Sends every order change to the live channels. The change is already
 * saved; if the realtime server is down, screens catch up on reconnect, so a
 * failure is reported and never undoes or blocks the order.
 */
final class BroadcastOrderChange
{
    public function handle(OrderStatusChanged|OrderUpdated $event): void
    {
        $message = $event instanceof OrderStatusChanged
            ? OrderBroadcast::for($event->order, $event->from === null ? 'placed' : 'status', $event->from?->value)
            : OrderBroadcast::for($event->order, $event->what);

        try {
            event($message);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
