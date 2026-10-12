<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small, dependency-free user-agent summary for the sessions screen.
 */
final class UserAgent
{
    /**
     * @return array{browser: string, platform: string, mobile: bool}
     */
    public static function describe(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => __('security.sessions.unknown_browser'),
        };

        $platform = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => __('security.sessions.unknown_platform'),
        };

        return [
            'browser' => $browser,
            'platform' => $platform,
            'mobile' => (bool) preg_match('/Mobile|iPhone|Android|iPad/i', $ua),
        ];
    }
}
