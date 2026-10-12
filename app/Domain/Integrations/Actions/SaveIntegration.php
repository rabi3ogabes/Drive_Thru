<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;

/**
 * Saves a restaurant's provider settings. Secrets are write-only: an empty
 * field keeps the saved value, so keys are never shown back in the form.
 * The audit log records what changed by name only, never the values.
 */
final class SaveIntegration
{
    /**
     * @param  array<string, ?string>  $credentials  new values; null or "" keeps the saved one
     * @param  array<string, mixed>  $options
     */
    public function handle(Restaurant $restaurant, IntegrationKind $kind, string $provider, string $mode, bool $enabled, array $credentials, array $options, ?User $actor): Integration
    {
        $integration = Integration::withoutTenancy()->firstOrNew(['restaurant_id' => $restaurant->id, 'kind' => $kind->value]);
        $saved = $integration->provider === $provider ? ($integration->credentials ?? []) : [];

        $changed = [];
        foreach ($credentials as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                $changed[] = $key;
                $saved[$key] = trim($value);
            }
        }

        $integration->forceFill([
            'restaurant_id' => $restaurant->id,
            'kind' => $kind,
            'provider' => $provider,
            'mode' => in_array($mode, [Integration::TEST, Integration::LIVE], true) ? $mode : Integration::TEST,
            'credentials' => $saved,
            'options' => $options,
            'is_enabled' => $enabled,
            // New keys need a new check.
            'verified_at' => $changed === [] ? $integration->verified_at : null,
        ])->save();

        activity()->performedOn($restaurant)->causedBy($actor)->event('integration.saved')
            ->withProperties(['kind' => $kind->value, 'provider' => $provider, 'mode' => $integration->mode, 'enabled' => $enabled, 'keys_changed' => $changed])
            ->log('integration.saved');

        return $integration;
    }
}
