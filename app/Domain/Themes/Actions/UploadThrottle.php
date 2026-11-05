<?php

declare(strict_types=1);

namespace App\Domain\Themes\Actions;

use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Theme uploads (fonts, covers, theme files): at most 20 an hour per person.
 */
final class UploadThrottle
{
    public const PER_HOUR = 20;

    public static function hit(User $user): void
    {
        $key = 'theme-upload:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw new ThemeRejected('slow_down', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]);
        }

        RateLimiter::hit($key, 3600);
    }
}
