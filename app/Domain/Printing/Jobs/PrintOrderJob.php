<?php

declare(strict_types=1);

namespace App\Domain\Printing\Jobs;

use App\Domain\Ordering\Models\Order;
use App\Domain\Printing\PrintOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PrintOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public readonly int $orderId, public readonly string $kind) {}

    public function handle(PrintOrder $print): void
    {
        $order = Order::withoutTenancy()->find($this->orderId);

        if ($order !== null && $print->handle($order, $this->kind) === PrintOrder::FAILED) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);
        }
    }
}
