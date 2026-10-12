<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Data;

/**
 * The cart as the server prices it: valid lines, any problems, and totals.
 * The checkout screen shows exactly these numbers, and PlaceOrder recomputes
 * them inside its transaction, so the guest never pays a stale price.
 */
final readonly class PricedCart
{
    /**
     * @param  list<PricedLine>  $lines
     * @param  list<Adjustment>  $adjustments  promotions, coupon, reward and points, in the order applied
     */
    public function __construct(
        public array $lines,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $serviceFeeMinor,
        public int $taxMinor,
        public int $totalMinor,
        public int $prepMinutes,
        public string $currency,
        public array $adjustments = [],
        public int $pointsRedeemed = 0,
        public int $pointsToEarn = 0,
        public ?string $benefitError = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function hasProblems(): bool
    {
        foreach ($this->lines as $line) {
            if (! $line->isValid()) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return array_sum(array_map(fn (PricedLine $line): int => $line->quantity, $this->lines));
    }
}
