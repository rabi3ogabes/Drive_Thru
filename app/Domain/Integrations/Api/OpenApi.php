<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Api;

use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Domain\Ordering\Enums\OrderStatus;

/**
 * The public API described as OpenAPI 3.1: served as JSON for tools and
 * rendered as the developer page. One source, so the docs can't drift.
 */
final class OpenApi
{
    /**
     * The endpoints in reading order, for the docs page.
     *
     * @return list<array{method: string, path: string, scope: ?string, summary: string, description: string, body?: array<string, mixed>, query?: array<string, string>}>
     */
    public static function endpoints(): array
    {
        return [
            ['method' => 'GET', 'path' => '/orders', 'scope' => ApiScope::OrdersRead->value, 'summary' => 'List orders', 'description' => 'Newest first, 25 per page (up to 100). Follow next_cursor for the next page.', 'query' => [
                'status' => 'Comma-separated: '.implode(', ', array_map(fn (OrderStatus $s): string => $s->value, OrderStatus::cases())),
                'branch' => 'Branch code',
                'since' => 'ISO 8601 time; orders placed at or after it',
                'per_page' => '1–100',
                'cursor' => 'next_cursor from the previous page',
            ]],
            ['method' => 'GET', 'path' => '/orders/{id}', 'scope' => ApiScope::OrdersRead->value, 'summary' => 'Get one order', 'description' => 'The order with its items, totals, branch and times.'],
            ['method' => 'POST', 'path' => '/orders/{id}/status', 'scope' => ApiScope::OrdersWrite->value, 'summary' => 'Move an order along', 'description' => 'preparing, ready, picked_up (only when paid) or cancelled (with a reason). Same rules as the staff screens; a refused move answers 409.', 'body' => ['status' => 'ready', 'reason' => null]],
            ['method' => 'GET', 'path' => '/branches', 'scope' => ApiScope::MenuRead->value, 'summary' => 'List branches', 'description' => 'Codes, bilingual names and locations.'],
            ['method' => 'GET', 'path' => '/branches/{code}/menu', 'scope' => ApiScope::MenuRead->value, 'summary' => 'Branch menu right now', 'description' => 'Categories and products with the branch price, availability and the reason when unavailable.'],
            ['method' => 'PUT', 'path' => '/branches/{code}/products/{id}/availability', 'scope' => ApiScope::MenuWrite->value, 'summary' => 'Switch a product on or off', 'description' => 'available, out_of_stock_today (back by itself at local midnight) or not_served.', 'body' => ['status' => 'out_of_stock_today']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        $paths = [];

        foreach (self::endpoints() as $endpoint) {
            $operation = [
                'summary' => $endpoint['summary'],
                'description' => $endpoint['description'],
                'security' => [['bearer' => $endpoint['scope'] === null ? [] : [$endpoint['scope']]]],
                'parameters' => array_values(array_merge(
                    array_map(fn (string $name): array => ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], self::pathParameters($endpoint['path'])),
                    array_map(fn (string $name, string $about): array => ['name' => $name, 'in' => 'query', 'required' => false, 'description' => $about, 'schema' => ['type' => 'string']], array_keys($endpoint['query'] ?? []), $endpoint['query'] ?? []),
                )),
                'responses' => [
                    '200' => ['description' => 'OK'],
                    '401' => ['description' => 'Missing or revoked key', '$ref' => '#/components/responses/Problem'],
                    '403' => ['description' => 'The key lacks the scope'],
                    '404' => ['description' => 'Not found in this restaurant'],
                    '429' => ['description' => 'Rate limit reached; see Retry-After'],
                ],
            ];

            if (isset($endpoint['body'])) {
                $operation['requestBody'] = ['required' => true, 'content' => ['application/json' => ['example' => $endpoint['body']]]];
                $operation['responses']['409'] = ['description' => 'The move is not allowed right now'];
                $operation['responses']['422'] = ['description' => 'Invalid input'];
            }

            $paths[$endpoint['path']][strtolower($endpoint['method'])] = $operation;
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => config('app.name').' API',
                'version' => '1.0.0',
                'description' => 'Orders and menus for one restaurant. Money is in minor units with the currency; names are {"en","ar"}; times are ISO 8601 UTC.',
            ],
            'servers' => [['url' => url('/api/v1')]],
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'An API key from Workspace → API keys.']],
                'responses' => ['Problem' => ['description' => 'Error', 'content' => ['application/json' => ['example' => ['error' => ['code' => 'unauthenticated', 'message' => '…']]]]]],
            ],
            'paths' => $paths,
            'webhooks' => collect(WebhookEvent::cases())->mapWithKeys(fn (WebhookEvent $event): array => [$event->value => ['post' => [
                'summary' => $event->value,
                'description' => 'Signed with X-DTP-Signature: t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<raw body>" with the endpoint secret>.',
                'responses' => ['2XX' => ['description' => 'Received']],
            ]]])->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function pathParameters(string $path): array
    {
        preg_match_all('/\{(\w+)\}/', $path, $matches);

        return $matches[1];
    }
}
