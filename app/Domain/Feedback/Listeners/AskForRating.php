<?php

declare(strict_types=1);

namespace App\Domain\Feedback\Listeners;

use App\Domain\Feedback\Jobs\SendRatingReminder;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;

/** Queues the one rating reminder, 30 minutes after the guest drove off. */
final class AskForRating
{
    public const DELAY_MINUTES = 30;

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::PickedUp || $event->order->customer_id === null) {
            return;
        }

        SendRatingReminder::dispatch($event->order->id)->delay(now()->addMinutes(self::DELAY_MINUTES))->afterCommit();
    }
}
