<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Exceptions;

use App\Support\Lang;
use RuntimeException;

/** A reward that can't be claimed or used right now; the message is ready for the guest. */
final class RewardUnavailable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self(Lang::text('loyalty.reward_errors.'.$reason));
    }
}
