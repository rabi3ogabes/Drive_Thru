<?php

declare(strict_types=1);

namespace App\Domain\Payments\Events;

use App\Domain\Ordering\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

final class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
