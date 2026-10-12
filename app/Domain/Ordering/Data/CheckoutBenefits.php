<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Data;

use App\Domain\Customers\Models\Customer;

/**
 * What the guest chose to use at checkout. Only ids, a code and a number of
 * points: the server decides what each is worth.
 */
final readonly class CheckoutBenefits
{
    public function __construct(
        public ?Customer $customer,
        public ?string $couponCode = null,
        public ?int $walletRewardId = null,
        public int $pointsToRedeem = 0,
    ) {}
}
