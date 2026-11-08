<?php

declare(strict_types=1);

namespace App\Domain\Support\Exceptions;

use App\Support\Lang;
use RuntimeException;

/** Why a ticket action can't go ahead, with a message the person can read. */
final class TicketRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(Lang::text('support.errors.'.$reason));
    }
}
