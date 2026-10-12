<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Webhooks;

use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\StaffAlerts;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One attempt at one delivery. 2xx is delivered; anything else waits for
 * the next retry (1 min … 12 h), then fails. Redirects are not followed and
 * the address is re-checked first, so a webhook can never be bent towards
 * an internal service. An endpoint with nothing but failures for 72 hours
 * is switched off and the owner is told.
 */
final class SendWebhookDelivery
{
    public function __construct(
        private readonly ProviderClient $client,
        private readonly SafeUrl $safeUrl,
        private readonly StaffAlerts $alerts,
    ) {}

    public function attempt(WebhookDelivery $delivery): WebhookDelivery
    {
        // Claim it: only one worker sends a given attempt.
        $claimed = WebhookDelivery::withoutTenancy()
            ->whereKey($delivery->id)
            ->where('status', WebhookDelivery::PENDING)
            ->where('attempts', $delivery->attempts)
            ->update(['attempts' => $delivery->attempts + 1, 'next_attempt_at' => null]);

        if ($claimed !== 1) {
            return $delivery->fresh() ?? $delivery;
        }

        $delivery->refresh();
        $endpoint = $delivery->endpoint()->withoutGlobalScopes()->first();

        if ($endpoint === null || ! $endpoint->is_active) {
            return $this->finish($delivery, null, 'endpoint_disabled', final: true);
        }

        if (! $this->safeUrl->allows($endpoint->url)) {
            return $this->finish($delivery, null, 'url_not_allowed', final: true);
        }

        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();

        try {
            $response = $this->client->call('webhook', $delivery->event, $delivery->restaurant_id, fn (PendingRequest $http) => $http
                ->withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => 'DriveThruPlus-Webhooks/1.0',
                    'X-DTP-Event' => $delivery->event,
                    'X-DTP-Delivery' => $delivery->event_id,
                    'X-DTP-Timestamp' => (string) $timestamp,
                    WebhookSignature::HEADER => WebhookSignature::header($endpoint->secret, $body, $timestamp),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url), (int) config('integrations.webhooks.timeout_seconds', 10));
        } catch (ProviderUnavailable) {
            return $this->finish($delivery, null, 'unreachable');
        }

        if ($response->successful()) {
            $delivery->forceFill([
                'status' => WebhookDelivery::DELIVERED,
                'response_status' => $response->status(),
                'response_excerpt' => Str::limit(trim($response->body()), 280),
                'delivered_at' => now(),
            ])->save();

            return $delivery;
        }

        return $this->finish($delivery, $response->status(), Str::limit(trim($response->body()), 280));
    }

    private function finish(WebhookDelivery $delivery, ?int $status, string $excerpt, bool $final = false): WebhookDelivery
    {
        $waits = WebhookDelivery::BACKOFF_MINUTES;
        $exhausted = $final || $delivery->attempts > count($waits);

        $delivery->forceFill([
            'status' => $exhausted ? WebhookDelivery::FAILED : WebhookDelivery::PENDING,
            'response_status' => $status,
            'response_excerpt' => $excerpt,
            'next_attempt_at' => $exhausted ? null : now()->addMinutes($waits[$delivery->attempts - 1]),
        ])->save();

        if ($exhausted) {
            $this->disableIfDead($delivery);
        }

        return $delivery;
    }

    /** No successful delivery for a long time, only failures: stop hammering a dead URL. */
    private function disableIfDead(WebhookDelivery $delivery): void
    {
        $since = now()->subHours((int) config('integrations.webhooks.disable_after_hours', 72));
        $endpoint = WebhookEndpoint::withoutTenancy()->find($delivery->webhook_endpoint_id);

        if ($endpoint === null || ! $endpoint->is_active || $endpoint->created_at?->isAfter($since)) {
            return;
        }

        $recentSuccess = WebhookDelivery::withoutTenancy()
            ->where('webhook_endpoint_id', $endpoint->id)
            ->where('status', WebhookDelivery::DELIVERED)
            ->where('delivered_at', '>=', $since)
            ->exists();

        if ($recentSuccess) {
            return;
        }

        $switchedOff = DB::table('webhook_endpoints')->where('id', $endpoint->id)->where('is_active', true)
            ->update(['is_active' => false, 'disabled_at' => now(), 'updated_at' => now()]);

        if ($switchedOff === 1) {
            activity()->performedOn($endpoint)->event('webhook.auto_disabled')->withProperties(['url' => $endpoint->url])->log('webhook.auto_disabled');
            $restaurant = Restaurant::query()->find($endpoint->restaurant_id);

            if ($restaurant !== null) {
                $this->alerts->send(AlertKind::WebhookDisabled, $restaurant->id, null, [
                    'url' => (string) parse_url($endpoint->url, PHP_URL_HOST),
                ], route('filament.app.resources.webhooks.index', ['tenant' => $restaurant]));
            }
        }
    }
}
