<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Integrations\Api\ApiScope;
use App\Domain\Integrations\Models\ApiClient;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public API's door: a bearer key that belongs to an active API client
 * (never a person's session), the scope the route needs, and the client's
 * restaurant as the tenant for everything that follows.
 */
final class AuthenticateApiClient
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $bearer = $request->bearerToken();
        $token = $bearer === null ? null : PersonalAccessToken::findToken($bearer);
        $client = $token?->tokenable;

        if (! $token instanceof PersonalAccessToken || ! $client instanceof ApiClient || ! $client->isActive() || $client->restaurant === null || ! $client->restaurant->isOperational()) {
            return $this->problem(401, 'unauthenticated', 'A valid API key is required (Authorization: Bearer <key>).');
        }

        $required = $scope === null ? null : ApiScope::from($scope);

        if ($required !== null && (! $client->allows($required) || ! $token->can($required->value))) {
            return $this->problem(403, 'insufficient_scope', "This key does not have the \"{$required->value}\" scope.");
        }

        $this->tenant->set($client->restaurant);
        $request->attributes->set('api_client', $client);

        // At most one write per minute per client for "last used".
        if ($client->last_used_at === null || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function problem(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
