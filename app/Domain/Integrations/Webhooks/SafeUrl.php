<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Webhooks;

/**
 * Keeps webhooks pointed at the public internet: HTTPS only, and never at
 * this server, its private network or cloud metadata addresses (checked when
 * saved and again before every delivery, since DNS can change).
 */
final class SafeUrl
{
    public function allows(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (! (bool) config('integrations.webhooks.block_private_networks', true)) {
            return true;
        }

        $host = trim($parts['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
