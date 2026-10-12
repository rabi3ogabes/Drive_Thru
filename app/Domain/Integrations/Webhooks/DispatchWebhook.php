<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Webhooks;

use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\Webhooks\Jobs\DeliverWebhook;
use Illuminate\Support\Str;

/**
 * Records an event for every active endpoint of the restaurant that wants
 * it, then sends each on the queue. The body is frozen now, so a retry an
 * hour later still describes the moment the event happened.
 */
final class DispatchWebhook
{
    public const PING = 'ping';

    /**
     * @param  array<string, mixed>  $data
     * @return int how many endpoints will receive it
     */
    public function handle(int $restaurantId, WebhookEvent $event, array $data): int
    {
        $endpoints = WebhookEndpoint::withoutTenancy()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->listensTo($event));

        if ($endpoints->isEmpty()) {
            return 0;
        }

        $eventId = (string) Str::uuid();
        $payload = [
            'id' => $eventId,
            'type' => $event->value,
            'created_at' => now()->toIso8601ZuluString(),
            'restaurant_id' => $restaurantId,
            'data' => $data,
        ];

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::withoutTenancy()->create([
                'restaurant_id' => $restaurantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_id' => $eventId,
                'event' => $event->value,
                'payload' => $payload,
                'next_attempt_at' => now(),
            ]);

            DeliverWebhook::dispatch($delivery->id)->afterCommit();
        }

        return $endpoints->count();
    }

    /** Sends a delivery again as a new attempt chain, with the same event id (receivers de-duplicate on it). */
    public function replay(WebhookDelivery $delivery): WebhookDelivery
    {
        $copy = WebhookDelivery::withoutTenancy()->create([
            'restaurant_id' => $delivery->restaurant_id,
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'event_id' => $delivery->event_id,
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'next_attempt_at' => now(),
        ]);

        DeliverWebhook::dispatch($copy->id)->afterCommit();

        return $copy;
    }

    /** A "ping" to check the receiving side, sent to one endpoint even if it listens to nothing else. */
    public function ping(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $eventId = (string) Str::uuid();
        $delivery = WebhookDelivery::withoutTenancy()->create([
            'restaurant_id' => $endpoint->restaurant_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_id' => $eventId,
            'event' => self::PING,
            'payload' => [
                'id' => $eventId,
                'type' => self::PING,
                'created_at' => now()->toIso8601ZuluString(),
                'restaurant_id' => $endpoint->restaurant_id,
                'data' => ['message' => 'Drive-Thru Plus is connected.'],
            ],
            'next_attempt_at' => now(),
        ]);

        DeliverWebhook::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }
}
