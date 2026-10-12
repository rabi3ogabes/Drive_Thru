<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Webhooks;

use SensitiveParameter;

/**
 * X-DTP-Signature: "t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">".
 * Receivers recompute it with their endpoint secret, compare in constant
 * time and reject timestamps older than five minutes (replay protection).
 */
final class WebhookSignature
{
    public const HEADER = 'X-DTP-Signature';

    public const TOLERANCE_SECONDS = 300;

    public static function header(#[SensitiveParameter] string $secret, string $body, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** The receiving side, as documented for integrators (and used in tests). */
    public static function verify(#[SensitiveParameter] string $secret, string $body, string $header, ?int $now = null): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = (int) ($parts['t'] ?? 0);

        if ($timestamp <= 0 || abs(($now ?? time()) - $timestamp) > self::TOLERANCE_SECONDS || ! isset($parts['v1'])) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $parts['v1']);
    }
}
