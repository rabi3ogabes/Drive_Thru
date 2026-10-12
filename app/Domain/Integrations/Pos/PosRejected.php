<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Pos;

use RuntimeException;

/** The POS answered, but not with a 2xx: retrying may help (5xx) or not (4xx). */
final class PosRejected extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public function isPermanent(): bool
    {
        return $this->status >= 400 && $this->status < 500 && $this->status !== 429;
    }
}
