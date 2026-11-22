<?php

declare(strict_types=1);

namespace App\Filament\Http;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filament's panel guard, with the right door instead of a bare 403: someone
 * already signed in who opens the other panel (a platform admin at /app, a
 * restaurant owner at /admin) is sent to the panel they can use; an account
 * linked to neither is told so and can sign out (the sign-out route is never
 * blocked). Deactivated accounts get 403.
 */
final class AuthenticatePanel extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        // Signing out is always allowed, whatever the account can open.
        if ($user !== null && $request->routeIs('filament.*.auth.logout')) {
            $this->auth->shouldUse(Filament::getAuthGuard());

            return;
        }

        if ($panel !== null && $user instanceof User && $user->is_active && ! $user->canAccessPanel($panel)) {
            $other = Filament::getPanel($panel->getId() === 'app' ? 'admin' : 'app');

            throw new HttpResponseException($user->canAccessPanel($other)
                ? redirect()->to(url($other->getPath()))
                : response()->view('errors.no-access', ['logoutUrl' => Filament::getLogoutUrl()], 403));
        }

        parent::authenticate($request, $guards);
    }
}
