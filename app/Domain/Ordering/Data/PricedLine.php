<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Data;

use App\Domain\Menu\Models\Product;

/**
 * One cart line after the server has re-priced and validated it.
 */
final readonly class PricedLine
{
    /**
     * @param  list<array{kind: string, group_id: int, option_id: int, group: array<string, string>, option: array<string, string>, price_minor: int}>  $options
     */
    public function __construct(
        public string $key,
        public ?Product $product,
        public int $quantity,
        public int $unitPriceMinor,
        public int $optionsMinor,
        public array $options,
        public ?string $notes,
        public ?string $error = null,
        public int $basePriceMinor = 0,
    ) {}

    public function totalMinor(): int
    {
        return ($this->unitPriceMinor + $this->optionsMinor) * $this->quantity;
    }

    public function isValid(): bool
    {
        return $this->error === null && $this->product !== null;
    }
}
