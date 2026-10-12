<?php

declare(strict_types=1);

namespace App\Domain\Customers\Exceptions;

use RuntimeException;

/** A friendly, translated reason a code couldn't be sent or accepted. */
final class OtpProblem extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?int $retryInSeconds = null,
    ) {
        parent::__construct($message);
    }
}
