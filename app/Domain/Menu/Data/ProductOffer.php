<?php

declare(strict_types=1);

namespace App\Domain\Menu\Data;

use App\Domain\Menu\Enums\Unavailability;

/**
 * What a branch offers for one product right now: whether it can be ordered
 * (and if not, why), the price before and after discount, and stock left.
 */
final readonly class ProductOffer
{
    public function __construct(
        public ?Unavailability $unavailable,
        public int $basePriceMinor,
        public int $priceMinor,
        public ?int $stockLeft,
        public bool $lowStock = false,
    ) {}

    public function isAvailable(): bool
    {
        return $this->unavailable === null;
    }

    public function isDiscounted(): bool
    {
        return $this->priceMinor < $this->basePriceMinor;
    }
}
