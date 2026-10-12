<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Data;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use Carbon\CarbonImmutable;

/**
 * What the guest asked for at checkout. Everything else (prices, totals,
 * codes, timings) is decided by the server in PlaceOrder.
 */
final readonly class OrderRequest
{
    /**
     * @param  list<array<string, mixed>>  $lines  cart lines (ids and choices only)
     * @param  array<string, string>  $source  e.g. QR campaign/channel/lane
     */
    public function __construct(
        public Customer $customer,
        public Branch $branch,
        public array $lines,
        public PaymentMethod $paymentMethod,
        public string $idempotencyKey,
        public OrderTiming $timing = OrderTiming::Now,
        public ?int $delayMinutes = null,
        public ?CarbonImmutable $scheduledFor = null,
        public ?int $vehicleId = null,
        public ?int $laneId = null,
        public ?string $notes = null,
        public array $source = [],
        public string $locale = 'ar',
        public ?CheckoutBenefits $benefits = null,
        /** The total the guest was shown; a different server total refuses the order. */
        public ?int $expectedTotalMinor = null,
    ) {}
}
