<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\Webhooks\SafeUrl;
use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes a webhook endpoint. New endpoints get a fresh signing
 * secret, returned once so it can be shown to the owner; it is never shown
 * again (only rotated). Every change is in the audit log, without secrets.
 */
final class SaveWebhookEndpoint
{
    public function __construct(private readonly SafeUrl $safeUrl) {}

    /**
     * @param  array<string, mixed>  $data  url, events, description (as entered in the form)
     * @return array{0: WebhookEndpoint, 1: ?string} the endpoint, and the secret when it was just created
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data, User $actor, ?WebhookEndpoint $endpoint = null): array
    {
        $url = is_string($data['url'] ?? null) ? trim($data['url']) : '';
        $events = array_values(array_intersect(WebhookEvent::values(), is_array($data['events'] ?? null) ? $data['events'] : []));

        if (! $this->safeUrl->allows($url)) {
            throw ValidationException::withMessages(['url' => __('integrations.webhooks.errors.url')]);
        }

        if ($events === []) {
            throw ValidationException::withMessages(['events' => __('integrations.webhooks.errors.events')]);
        }

        if ($endpoint === null && WebhookEndpoint::withoutTenancy()->where('restaurant_id', $restaurant->id)->count() >= (int) config('integrations.webhooks.max_endpoints', 10)) {
            throw ValidationException::withMessages(['url' => __('integrations.webhooks.errors.limit')]);
        }

        $secret = $endpoint === null ? WebhookEndpoint::newSecret() : null;
        $endpoint ??= new WebhookEndpoint(['restaurant_id' => $restaurant->id]);
        $endpoint->fill([
            'url' => $url,
            'events' => $events,
            'description' => is_string($data['description'] ?? null) && trim($data['description']) !== '' ? mb_substr(trim($data['description']), 0, 120) : null,
        ]);

        if ($secret !== null) {
            $endpoint->secret = $secret;
        }

        $created = ! $endpoint->exists;
        $endpoint->save();

        activity()->performedOn($endpoint)->causedBy($actor)
            ->event($created ? 'webhook.created' : 'webhook.updated')
            ->withProperties(['url' => $url, 'events' => $events])
            ->log($created ? 'webhook.created' : 'webhook.updated');

        return [$endpoint, $secret];
    }

    public function rotateSecret(WebhookEndpoint $endpoint, User $actor): string
    {
        $secret = WebhookEndpoint::newSecret();
        $endpoint->forceFill(['secret' => $secret])->save();
        activity()->performedOn($endpoint)->causedBy($actor)->event('webhook.secret_rotated')->log('webhook.secret_rotated');

        return $secret;
    }

    public function setActive(WebhookEndpoint $endpoint, bool $active, User $actor): void
    {
        $endpoint->forceFill(['is_active' => $active, 'disabled_at' => $active ? null : now()])->save();
        activity()->performedOn($endpoint)->causedBy($actor)->event($active ? 'webhook.enabled' : 'webhook.disabled')->log($active ? 'webhook.enabled' : 'webhook.disabled');
    }
}
