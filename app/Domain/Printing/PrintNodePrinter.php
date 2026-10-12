<?php

declare(strict_types=1);

namespace App\Domain\Printing;

use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Printing\Contracts\Printer;

/**
 * PrintNode: a small client on the branch computer prints to the kitchen and
 * receipt printers; we send PDF jobs through its API with the restaurant's key.
 */
final class PrintNodePrinter implements Printer
{
    public const NAME = 'printnode';

    private const API = 'https://api.printnode.com';

    public function __construct(
        private readonly Integration $integration,
        private readonly ProviderClient $client,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function send(int $restaurantId, string $printerId, string $title, string $pdf): bool
    {
        try {
            $response = $this->client->call(self::NAME, 'print', $restaurantId, fn ($http) => $http
                ->withBasicAuth((string) $this->integration->credential('api_key'), '')
                ->acceptJson()
                ->post(self::API.'/printjobs', [
                    'printerId' => (int) $printerId,
                    'title' => $title,
                    'contentType' => 'pdf_base64',
                    'content' => base64_encode($pdf),
                    'source' => (string) config('app.name'),
                ]));
        } catch (ProviderUnavailable) {
            return false;
        }

        return $response->successful();
    }

    public function verifyConnection(): bool
    {
        try {
            return $this->client->call(self::NAME, 'whoami', $this->integration->restaurant_id, fn ($http) => $http
                ->withBasicAuth((string) $this->integration->credential('api_key'), '')
                ->acceptJson()
                ->get(self::API.'/whoami'))->successful();
        } catch (ProviderUnavailable) {
            return false;
        }
    }
}
