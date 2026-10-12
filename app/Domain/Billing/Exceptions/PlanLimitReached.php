<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

/** Raised when adding a branch or employee would go over the plan. */
final class PlanLimitReached extends RuntimeException
{
    public function __construct(
        public readonly string $key,
        public readonly int $limit,
        public readonly ?string $planName = null,
    ) {
        parent::__construct(self::friendly($key, $limit, $planName));
    }

    public static function friendly(string $key, int $limit, ?string $planName): string
    {
        return trans_choice("billing.limits.{$key}.reached", $limit, [
            'count' => $limit,
            'plan' => $planName ?? '—',
        ]);
    }
}
