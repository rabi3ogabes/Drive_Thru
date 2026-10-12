<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\Models\ProviderCall;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The only way drivers talk to outside providers: a timeout, one retry on
 * network errors, and a record of every call (provider, operation, status,
 * duration, error) for the health page and failure alerts. Request bodies
 * and secrets are never logged.
 */
final class ProviderClient
{
    /**
     * @param  Closure(PendingRequest): Response  $send
     *
     * @throws ProviderUnavailable when the provider can't be reached
     */
    public function call(string $provider, string $operation, ?int $restaurantId, Closure $send, int $timeout = 15): Response
    {
        $started = hrtime(true);
        $request = Http::timeout($timeout)->connectTimeout(5)->retry(2, 300, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false);

        try {
            $response = $send($request);
        } catch (ConnectionException $e) {
            $this->record($provider, $operation, $restaurantId, false, null, $started, 'connection: '.$e->getMessage());

            throw ProviderUnavailable::because($provider, 'unreachable');
        }

        $this->record(
            $provider,
            $operation,
            $restaurantId,
            $response->successful(),
            $response->status(),
            $started,
            $response->successful() ? null : Str::limit(trim($response->body()), 280),
        );

        return $response;
    }

    private function record(string $provider, string $operation, ?int $restaurantId, bool $ok, ?int $status, int|float $started, ?string $error): void
    {
        try {
            ProviderCall::withoutTenancy()->create([
                'restaurant_id' => $restaurantId,
                'provider' => $provider,
                'operation' => $operation,
                'ok' => $ok,
                'http_status' => $status,
                'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'error' => $error === null ? null : Str::limit($error, 280),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e); // Logging must never break the call itself.
        }
    }
}
