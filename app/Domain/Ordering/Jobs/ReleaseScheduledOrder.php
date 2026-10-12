<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Jobs;

use App\Domain\Ordering\Actions\ReleaseScheduledOrders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Queued for a held order's start time; the minute sweep is the safety net. */
final class ReleaseScheduledOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $orderId) {}

    public function handle(ReleaseScheduledOrders $release): void
    {
        $release->handle($this->orderId);
    }
}
