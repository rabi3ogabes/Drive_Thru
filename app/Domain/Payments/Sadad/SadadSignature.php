<?php

declare(strict_types=1);

namespace App\Domain\Payments\Sadad;

/**
 * Sadad's checksum, exactly as documented for Web Checkout 2.1: the secret
 * key followed by the parameter values (no keys, no separators), with the
 * parameters sorted by key name, hashed with SHA-256. Requests send it in
 * upper case; callbacks and webhooks are compared case-insensitively.
 * Values are never URL-encoded before hashing.
 */
final class SadadSignature
{
    public const FIELD = 'checksumhash';

    /**
     * @param  array<string, scalar|null>  $params
     */
    public function sign(array $params, string $secretKey): string
    {
        return strtoupper($this->hash($params, $secretKey));
    }

    /**
     * Checks a callback or webhook. JSON webhooks may turn an amount such as
     * "150.00" into a number; for amount fields both the raw value and the
     * two-decimal form are accepted.
     *
     * @param  array<string, mixed>  $params  everything Sadad sent, including checksumhash
     */
    public function verify(array $params, string $secretKey): bool
    {
        $received = $params[self::FIELD] ?? null;

        if (! is_string($received) || $received === '') {
            return false;
        }

        unset($params[self::FIELD]);
        $flat = array_filter($params, fn (mixed $value): bool => is_scalar($value) || $value === null);

        $candidates = [$this->hash($flat, $secretKey)];

        $decimals = [];
        foreach ($flat as $key => $value) {
            $decimals[$key] = (is_int($value) || is_float($value)) && str_contains(strtolower((string) $key), 'amount')
                ? number_format((float) $value, 2, '.', '')
                : $value;
        }
        if ($decimals !== $flat) {
            $candidates[] = $this->hash($decimals, $secretKey);
        }

        foreach ($candidates as $candidate) {
            if (hash_equals($candidate, strtolower($received))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function hash(array $params, string $secretKey): string
    {
        ksort($params, SORT_STRING);
        $string = $secretKey;

        foreach ($params as $value) {
            $string .= match (true) {
                $value === null => '',
                is_bool($value) => $value ? '1' : '0',
                default => (string) $value,
            };
        }

        return hash('sha256', $string);
    }
}
