<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Unsaved theme drafts for the live preview. A draft lives 30 minutes under
 * a random token and shows only to the person who made it, on their own
 * restaurant's pages; anyone else with the link sees the saved theme.
 */
final class ThemePreview
{
    public const MINUTES = 30;

    public static function put(Restaurant $restaurant, User $user, ThemeSpec $spec): string
    {
        $token = Str::random(40);

        Cache::put(self::key($token), ['restaurant' => $restaurant->id, 'user' => $user->id, 'spec' => $spec->toArray()], now()->addMinutes(self::MINUTES));

        return $token;
    }

    public static function draft(Restaurant $restaurant, mixed $token): ?ThemeSpec
    {
        if (! is_string($token) || preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1 || auth()->id() === null) {
            return null;
        }

        $draft = Cache::get(self::key($token));

        if (! is_array($draft) || ($draft['restaurant'] ?? null) !== $restaurant->id || ($draft['user'] ?? null) !== auth()->id() || ! is_array($draft['spec'] ?? null)) {
            return null;
        }

        try {
            return ThemeSpec::fromArray($draft['spec']);
        } catch (ThemeRejected) {
            return null;
        }
    }

    private static function key(string $token): string
    {
        return "theme-preview:{$token}";
    }
}
