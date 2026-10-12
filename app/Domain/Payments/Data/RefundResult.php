<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

final readonly class RefundResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $reference = null,
        public ?string $failureReason = null,
    ) {}

    public static function succeeded(?string $reference = null): self
    {
        return new self(true, $reference);
    }

    public static function failed(string $reason): self
    {
        return new self(false, failureReason: $reason);
    }
}
