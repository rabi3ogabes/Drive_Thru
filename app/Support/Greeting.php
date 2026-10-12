<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * "Good evening, Sara": a time-aware greeting in the viewer's timezone.
 */
final class Greeting
{
    public static function for(?string $fullName, ?string $timezone = null): string
    {
        $hour = CarbonImmutable::now($timezone ?? (string) config('app.display_timezone'))->hour;

        $key = match (true) {
            $hour >= 5 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 17 => 'afternoon',
            default => 'evening',
        };

        $firstName = Str::of((string) $fullName)->trim()->explode(' ')->first();

        return filled($firstName)
            ? __("app.greeting.{$key}_name", ['name' => $firstName])
            : __("app.greeting.{$key}");
    }
}
