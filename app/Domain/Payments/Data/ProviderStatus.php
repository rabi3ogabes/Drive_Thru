<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/** Where the provider says a payment stands. */
final readonly class ProviderStatus
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public const UNKNOWN = 'unknown';

    public function __construct(
        public string $state,
        public ?string $transaction = null,
        public ?int $amountMinor = null,
        public ?int $feeMinor = null,
        public ?string $reason = null,
    ) {}

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }

    public function isPaid(): bool
    {
        return $this->state === self::PAID;
    }

    public function isFailed(): bool
    {
        return $this->state === self::FAILED;
    }

    public function isPending(): bool
    {
        return $this->state === self::PENDING;
    }
}
