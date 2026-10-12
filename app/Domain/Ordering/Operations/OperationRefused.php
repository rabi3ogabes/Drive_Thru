<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Operations;

use App\Support\Lang;
use RuntimeException;

/** Staff can't do this right now; the message is ready to show on the screen. */
final class OperationRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $reason): self
    {
        return new self($reason, Lang::text("ops.refused.{$reason}"));
    }
}
