<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Domain\Security\LoginThrottle;
use App\Models\User;
use App\Support\Lang;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Filament's sign-in page plus brute-force lockout: a locked email or IP is
 * refused before its password is ever checked. Failures and successes are
 * recorded by the auth event listeners, so every sign-in path is covered.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $email = data_get($this->data, 'email');
        $lockedUntil = app(LoginThrottle::class)->lockedUntil(is_string($email) ? $email : null, request()->ip());

        if ($lockedUntil !== null) {
            throw ValidationException::withMessages([
                'data.email' => __('security.login.locked', [
                    'minutes' => max(1, (int) ceil(now()->diffInSeconds($lockedUntil) / 60)),
                ]),
            ]);
        }

        if (($hint = $this->otherPanelHint()) !== null) {
            throw ValidationException::withMessages(['data.email' => $hint]);
        }

        return parent::authenticate();
    }

    /**
     * Right email and password, wrong door: a platform admin on the
     * restaurant sign-in (or a restaurant team member on the platform's)
     * is told where to sign in instead of "credentials do not match".
     * Only with the correct password, so it never reveals that an email
     * exists; and never signs in here, so two-step sign-in still applies.
     */
    private function otherPanelHint(): ?string
    {
        $email = data_get($this->data, 'email');
        $password = data_get($this->data, 'password');
        $panel = Filament::getCurrentPanel();

        if (! is_string($email) || ! is_string($password) || $password === '' || $panel === null || ! in_array($panel->getId(), ['admin', 'app'], true)) {
            return null;
        }

        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if ($user === null || ! $user->is_active || ! Hash::check($password, $user->password) || $user->canAccessPanel($panel)) {
            return null;
        }

        $other = Filament::getPanel($panel->getId() === 'app' ? 'admin' : 'app');

        return $user->canAccessPanel($other)
            ? Lang::text('security.login.other_panel.'.$other->getId(), ['url' => $other->getLoginUrl()])
            : null;
    }

    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return __('security.login.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return __('security.login.subheading');
    }
}
