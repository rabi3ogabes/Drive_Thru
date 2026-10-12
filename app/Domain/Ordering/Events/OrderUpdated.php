<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Events;

use App\Domain\Ordering\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something about an order changed without a status change: the guest is on
 * the way or has arrived, shared a location, a station finished its items,
 * or payment was collected. Screens and the guest page refresh on it.
 */
final class OrderUpdated
{
    use Dispatchable;

    public const ON_THE_WAY = 'on_the_way';

    public const ARRIVED = 'arrived';

    public const LOCATION = 'location';

    public const APPROACHING = 'approaching';

    public const STATION_DONE = 'station_done';

    public const PAID = 'paid';

    /** The restaurant's POS accepted the order (or couldn't). */
    public const POS = 'pos';

    public function __construct(
        public readonly Order $order,
        public readonly string $what,
    ) {}
}
