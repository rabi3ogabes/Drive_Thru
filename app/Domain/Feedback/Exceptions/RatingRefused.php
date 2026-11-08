<?php

declare(strict_types=1);

namespace App\Domain\Feedback\Exceptions;

use App\Support\Lang;
use RuntimeException;

/** Why a rating can't be saved, with a message the guest can read. */
final class RatingRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(Lang::text('feedback.errors.'.$reason));
    }
}
