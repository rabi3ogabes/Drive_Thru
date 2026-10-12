<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Domain\Security\LoginThrottle;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
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

        return parent::authenticate();
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
