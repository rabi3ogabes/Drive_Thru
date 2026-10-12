<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Api\ApiScope;
use App\Domain\Integrations\Models\ApiClient;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an API client with its key (returned once, never stored in plain
 * text), and revokes clients. Both are in the audit log.
 */
final class IssueApiClient
{
    /**
     * @param  list<string>  $scopes
     * @return array{0: ApiClient, 1: string} the client and its key
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, string $name, array $scopes, User $actor): array
    {
        $scopes = array_values(array_intersect(ApiScope::values(), $scopes));

        if ($scopes === []) {
            throw ValidationException::withMessages(['scopes' => __('integrations.api.errors.scopes')]);
        }

        $active = ApiClient::withoutTenancy()->where('restaurant_id', $restaurant->id)->whereNull('revoked_at')->count();

        if ($active >= (int) config('integrations.api.max_clients', 20)) {
            throw ValidationException::withMessages(['name' => __('integrations.api.errors.limit')]);
        }

        return DB::transaction(function () use ($restaurant, $name, $scopes, $actor): array {
            $client = ApiClient::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'name' => mb_substr(trim($name), 0, 80),
                'scopes' => $scopes,
                'created_by' => $actor->id,
            ]);

            $key = $client->createToken('api', $scopes)->plainTextToken;

            activity()->performedOn($client)->causedBy($actor)->event('api_client.created')->withProperties(['name' => $client->name, 'scopes' => $scopes])->log('api_client.created');

            return [$client, $key];
        });
    }

    public function revoke(ApiClient $client, User $actor): void
    {
        DB::transaction(function () use ($client, $actor): void {
            $client->tokens()->delete();
            $client->forceFill(['revoked_at' => now()])->save();
            activity()->performedOn($client)->causedBy($actor)->event('api_client.revoked')->withProperties(['name' => $client->name])->log('api_client.revoked');
        });
    }
}
