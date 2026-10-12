<?php

declare(strict_types=1);

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;

/** A refund finished at the provider (succeeded or failed: see $refund->status). */
final class PaymentRefunded
{
    use Dispatchable;

    public function __construct(public readonly Refund $refund) {}
}
