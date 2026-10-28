<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Exceptions;

use App\Support\Lang;
use RuntimeException;

/** A campaign change that can't happen; the message is ready to show. */
final class CampaignProblem extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self(Lang::text('campaigns.errors.'.$reason));
    }
}
