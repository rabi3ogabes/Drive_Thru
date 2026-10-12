<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

final readonly class ChargeResult
{
    private function __construct(
        public string $outcome,
        public ?string $reference = null,
        public ?string $redirectUrl = null,
        public ?string $failureReason = null,
    ) {}

    public static function paid(string $reference): self
    {
        return new self('paid', $reference);
    }

    public static function failed(string $reason, ?string $reference = null): self
    {
        return new self('failed', $reference, failureReason: $reason);
    }

    public static function redirect(string $reference, string $url): self
    {
        return new self('redirect', $reference, $url);
    }

    public function isPaid(): bool
    {
        return $this->outcome === 'paid';
    }

    public function needsRedirect(): bool
    {
        return $this->outcome === 'redirect';
    }
}
