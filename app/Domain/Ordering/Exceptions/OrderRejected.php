<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Exceptions;

use App\Support\Lang;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * An order can't be placed as asked. The message is ready to show the guest;
 * `$nextSlot` suggests another time when the kitchen was simply full.
 */
final class OrderRejected extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?CarbonImmutable $nextSlot = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $reason, array $replace = [], ?CarbonImmutable $nextSlot = null): self
    {
        return new self($reason, Lang::text("ordering.errors.{$reason}", $replace), $nextSlot);
    }
}
