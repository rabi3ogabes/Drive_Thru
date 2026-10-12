<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Events;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after every status change (Phase 5 broadcasts it to screens and guests). */
final class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly ?OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}
}
