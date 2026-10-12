<?php

declare(strict_types=1);

namespace App\Domain\Security\Listeners;

use App\Domain\Access\Impersonation;
use App\Domain\Security\LoginThrottle;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

final class HandleSuccessfulSignIn
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly Request $request,
    ) {}

    public function handle(Login $event): void
    {
        // Switching identity for "view as owner" is not a real sign-in.
        if (! $event->user instanceof User || Impersonation::isSwitching()) {
            return;
        }

        $this->throttle->clear($event->user->email, $this->request->ip());

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->saveQuietly();
    }
}
