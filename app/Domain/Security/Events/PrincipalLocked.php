<?php

declare(strict_types=1);

namespace App\Domain\Security\Events;

use App\Domain\Security\Enums\SecurityAlertType;
use Illuminate\Foundation\Events\Dispatchable;

final class PrincipalLocked
{
    use Dispatchable;

    public function __construct(
        public readonly SecurityAlertType $type,
        public readonly ?string $maskedEmail,
        public readonly ?string $ipHash,
        public readonly int $attemptCount,
        public readonly ?string $userAgent,
        public readonly ?string $principal = null,
    ) {}
}
