<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Push;

use App\Domain\Notifications\Models\PushSubscription;

/** Delivers one encrypted push message to one browser. */
interface PushTransport
{
    public const SENT = 'sent';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    public function configured(): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return string one of SENT, EXPIRED, FAILED
     */
    public function send(PushSubscription $subscription, array $payload): string;
}
