<?php

declare(strict_types=1);

namespace App\Domain\Services\Exceptions;

use App\Domain\Services\Enums\Service;
use App\Support\Lang;
use RuntimeException;

/** A service change that can't happen; the message is ready to show. */
final class ServiceProblem extends RuntimeException
{
    public static function because(string $reason, Service $service): self
    {
        return new self(Lang::text('services.errors.'.$reason, ['service' => $service->getLabel()]));
    }
}
