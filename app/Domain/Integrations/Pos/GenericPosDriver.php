<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Pos;

use App\Domain\Integrations\Api\OrderPayload;
use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Integrations\Webhooks\SafeUrl;
use App\Domain\Integrations\Webhooks\WebhookSignature;
use App\Domain\Ordering\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/**
 * Any POS that can take orders over HTTPS: we POST the order (the same JSON
 * as the API and webhooks) to its order URL with its key as a bearer token
 * and an X-DTP-Signature, and keep the reference it answers with
 * ({"reference": "…"} or {"id": "…"}). The same order id is always sent
 * again on retries, so the POS can ignore duplicates.
 */
final class GenericPosDriver implements PosDriver
{
    public const NAME = 'generic';

    public function __construct(
        private readonly Integration $integration,
        private readonly ProviderClient $client,
        private readonly SafeUrl $safeUrl,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function pushOrder(Order $order): ?string
    {
        $response = $this->post('push_order', [
            'type' => 'order',
            'id' => 'order-'.$order->id,
            'order' => OrderPayload::from($order),
        ]);

        $reference = $response['reference'] ?? $response['id'] ?? null;

        return is_scalar($reference) && (string) $reference !== '' ? Str::limit((string) $reference, 80, '') : null;
    }

    public function verifyConnection(): bool
    {
        try {
            $this->post('ping', ['type' => 'ping', 'id' => 'ping-'.Str::uuid()]);

            return true;
        } catch (PosRejected|ProviderUnavailable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws PosRejected|ProviderUnavailable
     */
    private function post(string $operation, array $body): array
    {
        $url = (string) $this->integration->credential('order_url');
        $key = (string) $this->integration->credential('api_key');

        if (! $this->safeUrl->allows($url) || $key === '') {
            throw new PosRejected(0, 'not_configured');
        }

        $json = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();

        $response = $this->client->call('pos', $operation, $this->integration->restaurant_id, fn (PendingRequest $http) => $http
            ->withoutRedirecting()
            ->withToken($key)
            ->withHeaders(['User-Agent' => 'DriveThruPlus-POS/1.0', WebhookSignature::HEADER => WebhookSignature::header($key, $json, $timestamp)])
            ->accept('application/json')
            ->withBody($json, 'application/json')
            ->post($url), 15);

        if (! $response->successful()) {
            throw new PosRejected($response->status(), Str::limit(trim($response->body()), 180));
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
