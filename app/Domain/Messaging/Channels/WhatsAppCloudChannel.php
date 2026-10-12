<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channels;

use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Messaging\Contracts\MessageChannel;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Data\SendResult;
use App\Domain\Messaging\Models\MessageDelivery;

/**
 * WhatsApp through Meta's Cloud API, with approved templates: an
 * authentication template for login codes (with the copy-code button) and
 * utility templates for order updates. Delivery reports arrive at the
 * signed webhook (WhatsAppWebhookController).
 */
final class WhatsAppCloudChannel implements MessageChannel
{
    public const NAME = 'whatsapp';

    public function __construct(private readonly ProviderClient $client) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function available(): bool
    {
        return filled(config('messaging.whatsapp.phone_number_id')) && filled(config('messaging.whatsapp.access_token'));
    }

    public function send(Message $message, MessageDelivery $delivery): SendResult
    {
        $template = config('messaging.whatsapp.templates.'.$message->purpose);

        if (! is_string($template) || $template === '') {
            return SendResult::failed('no_template');
        }

        $components = [];

        if ($message->parameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => $value], $message->parameters),
            ];
        }

        if ($message->buttonParameter !== null) {
            // Authentication templates: the copy-code button carries the code too.
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $message->buttonParameter]]];
        }

        $languages = (array) config('messaging.whatsapp.languages');
        $url = sprintf('https://graph.facebook.com/%s/%s/messages', config('messaging.whatsapp.api_version'), config('messaging.whatsapp.phone_number_id'));

        try {
            $response = $this->client->call(self::NAME, $message->purpose, $message->restaurantId, fn ($http) => $http
                ->withToken((string) config('messaging.whatsapp.access_token'))
                ->acceptJson()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($message->phone, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => $languages[$message->locale] ?? $message->locale],
                        'components' => $components,
                    ],
                ]));
        } catch (ProviderUnavailable) {
            return SendResult::failed('unreachable');
        }

        $id = $response->json('messages.0.id');

        if (! $response->successful() || ! is_string($id)) {
            return SendResult::failed((string) ($response->json('error.message') ?? 'http_'.$response->status()));
        }

        return SendResult::sent($id);
    }
}
