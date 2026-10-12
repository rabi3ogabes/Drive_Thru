<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Exceptions;

use RuntimeException;

/** A provider could not be reached or refused the request. Never contains secrets. */
final class ProviderUnavailable extends RuntimeException
{
    public static function because(string $provider, string $reason): self
    {
        return new self("{$provider}: {$reason}");
    }
}
