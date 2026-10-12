<?php

declare(strict_types=1);

namespace App\Domain\Security\Listeners;

use App\Domain\Security\LoginThrottle;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

final class RecordFailedSignIn
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly Request $request,
    ) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->throttle->recordFailure(
            is_string($email) ? $email : null,
            $this->request->ip(),
            $this->request->userAgent(),
        );
    }
}
