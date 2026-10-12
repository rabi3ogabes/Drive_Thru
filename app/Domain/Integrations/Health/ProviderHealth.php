<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Health;

use App\Domain\Integrations\Models\ProviderCall;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * How every outside provider is doing, from the calls we made to it: volume,
 * success rate, speed and the last error, per provider, across all
 * restaurants. Feeds the platform health page and the failure watcher.
 */
final class ProviderHealth
{
    public const HEALTHY = 'healthy';

    public const DEGRADED = 'degraded';

    public const DOWN = 'down';

    public const IDLE = 'idle';

    /** Providers we talk to, in the order the page lists them. */
    public const PROVIDERS = ['sadad', 'whatsapp', 'smsala', 'webpush', 'printnode', 'pos', 'webhook'];

    /**
     * @return list<array{provider: string, calls: int, failures: int, success_rate: ?float, avg_ms: ?int, max_ms: ?int, last_call_at: ?CarbonImmutable, last_error: ?string, last_error_at: ?CarbonImmutable, restaurants: int, status: string}>
     */
    public function summary(CarbonImmutable $since): array
    {
        $rows = ProviderCall::withoutTenancy()
            ->where('created_at', '>=', $since)
            ->groupBy('provider')
            ->get([
                'provider',
                DB::raw('count(*) as calls'),
                DB::raw('sum(case when ok then 0 else 1 end) as failures'),
                DB::raw('avg(duration_ms) as avg_ms'),
                DB::raw('max(duration_ms) as max_ms'),
                DB::raw('max(created_at) as last_call_at'),
                DB::raw('count(distinct restaurant_id) as restaurants'),
            ])
            ->keyBy('provider');

        $lastErrors = ProviderCall::withoutTenancy()
            ->where('ok', false)
            ->whereIn('id', ProviderCall::withoutTenancy()->where('ok', false)->where('created_at', '>=', $since)->groupBy('provider')->selectRaw('max(id)'))
            ->get(['provider', 'error', 'http_status', 'created_at'])
            ->keyBy('provider');

        $providers = array_values(array_unique([...self::PROVIDERS, ...$rows->keys()->all()]));

        return array_map(function (string $provider) use ($rows, $lastErrors): array {
            $row = $rows->get($provider);
            $calls = (int) ($row?->getAttribute('calls') ?? 0);
            $failures = (int) ($row?->getAttribute('failures') ?? 0);
            $error = $lastErrors->get($provider);

            return [
                'provider' => $provider,
                'calls' => $calls,
                'failures' => $failures,
                'success_rate' => $calls > 0 ? round(($calls - $failures) / $calls * 100, 1) : null,
                'avg_ms' => $row === null ? null : (int) round((float) $row->getAttribute('avg_ms')),
                'max_ms' => $row === null ? null : (int) $row->getAttribute('max_ms'),
                'last_call_at' => $row?->getAttribute('last_call_at') === null ? null : CarbonImmutable::parse((string) $row->getAttribute('last_call_at')),
                'last_error' => $error === null ? null : trim(($error->http_status !== null ? 'HTTP '.$error->http_status.' · ' : '').($error->error ?? '')),
                'last_error_at' => $error?->created_at,
                'restaurants' => (int) ($row?->getAttribute('restaurants') ?? 0),
                'status' => self::status($calls, $failures),
            ];
        }, $providers);
    }

    public static function status(int $calls, int $failures): string
    {
        if ($calls === 0) {
            return self::IDLE;
        }

        $rate = $failures / $calls;

        return match (true) {
            $calls >= 3 && $rate >= 0.5 => self::DOWN,
            $rate >= 0.1 => self::DEGRADED,
            default => self::HEALTHY,
        };
    }
}
