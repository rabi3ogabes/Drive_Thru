<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Pos;

use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;

/** An order the kitchen will make (received: paid, pay at the window, or a held order released) goes to the POS. */
final class SendAcceptedOrdersToPos
{
    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Received || $event->order->pos_synced_at !== null) {
            return;
        }

        $connected = Integration::withoutTenancy()
            ->where('restaurant_id', $event->order->restaurant_id)
            ->where('kind', IntegrationKind::Pos->value)
            ->where('is_enabled', true)
            ->exists();

        if ($connected) {
            PushOrderToPos::dispatch($event->order->id)->afterCommit();
        }
    }
}
