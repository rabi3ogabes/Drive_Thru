<?php

declare(strict_types=1);

namespace App\Domain\Printing\Listeners;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Printing\Jobs\PrintOrderJob;
use App\Domain\Printing\TicketRenderer;

/** Branches with auto-print get a kitchen ticket the moment an order reaches the kitchen. */
final class AutoPrintKitchenTicket
{
    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Received) {
            return;
        }

        $event->order->loadMissing('branch');

        if ($event->order->branch->auto_print) {
            PrintOrderJob::dispatch($event->order->id, TicketRenderer::KITCHEN);
        }
    }
}
