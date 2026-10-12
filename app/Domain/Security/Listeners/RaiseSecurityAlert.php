<?php

declare(strict_types=1);

namespace App\Domain\Security\Listeners;

use App\Domain\Security\Events\PrincipalLocked;
use App\Domain\Security\Models\SecurityAlert;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Traits\Localizable;

/**
 * Stores the alert and pings every active super admin in their own language.
 */
final class RaiseSecurityAlert
{
    use Localizable;

    public function handle(PrincipalLocked $event): void
    {
        SecurityAlert::create([
            'type' => $event->type,
            'severity' => 'critical',
            'attempted_email' => $event->maskedEmail,
            'ip_hash' => $event->ipHash,
            'attempt_count' => $event->attemptCount,
            'details' => [
                'user_agent' => mb_substr((string) $event->userAgent, 0, 300),
                'lock_minutes' => (int) config('security.login.lock_minutes'),
                'principal' => $event->principal,
            ],
        ]);

        User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->each(function (User $admin) use ($event): void {
                $this->withLocale($admin->locale ?? config('app.locale'), function () use ($admin, $event): void {
                    Notification::make()
                        ->title(__("security.alerts.{$event->type->value}.title"))
                        ->body(__("security.alerts.{$event->type->value}.body", [
                            'email' => $event->maskedEmail ?? '—',
                            'count' => $event->attemptCount,
                            'minutes' => (int) config('security.login.lock_minutes'),
                        ]))
                        ->icon('heroicon-o-shield-exclamation')
                        ->danger()
                        ->sendToDatabase($admin);
                });
            });
    }
}
