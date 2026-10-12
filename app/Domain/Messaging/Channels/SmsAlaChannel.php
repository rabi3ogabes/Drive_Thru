<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channels;

use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Messaging\Contracts\MessageChannel;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Data\SendResult;
use App\Domain\Messaging\Models\MessageDelivery;
use Illuminate\Support\Facades\URL;

/**
 * SMS through SMSala (Qatar). Login codes go as the OTP message type,
 * everything else as transactional; Arabic text uses UCS-2. Delivery
 * reports come back to a signed callback URL carrying the delivery id
 * (SMSala does not sign its callbacks).
 */
final class SmsAlaChannel implements MessageChannel
{
    public const NAME = 'sms';

    private const TYPE_TRANSACTIONAL = '2';

    private const TYPE_OTP = '3';

    private const ENCODING_DEFAULT = '0';

    private const ENCODING_UCS2 = '8';

    public function __construct(private readonly ProviderClient $client) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function available(): bool
    {
        return filled(config('messaging.smsala.api_token')) && filled(config('messaging.smsala.sender_id'));
    }

    public function send(Message $message, MessageDelivery $delivery): SendResult
    {
        $unicode = preg_match('/[^\x00-\x7F]/', $message->text) === 1;

        try {
            $response = $this->client->call('smsala', $message->purpose, $message->restaurantId, fn ($http) => $http
                ->acceptJson()
                ->post(rtrim((string) config('messaging.smsala.base_url'), '/').'/SendSmsV2', [
                    'apiToken' => (string) config('messaging.smsala.api_token'),
                    'messageType' => $message->isOtp() ? self::TYPE_OTP : self::TYPE_TRANSACTIONAL,
                    'messageEncoding' => $unicode ? self::ENCODING_UCS2 : self::ENCODING_DEFAULT,
                    'destinationAddress' => ltrim($message->phone, '+'),
                    'sourceAddress' => (string) config('messaging.smsala.sender_id'),
                    'messageText' => $message->text,
                    'callBackUrl' => URL::signedRoute('webhooks.smsala', ['delivery' => $delivery->id]),
                    'userReferenceId' => (string) $delivery->id,
                ]));
        } catch (ProviderUnavailable) {
            return SendResult::failed('unreachable');
        }

        $first = $response->json('0') ?? $response->json();
        $status = is_array($first) ? ($first['Status'] ?? null) : null;
        $id = is_array($first) ? ($first['MessageId'] ?? null) : null;

        if (! $response->successful() || $status !== 'Success') {
            $remarks = is_array($first) ? ($first['Remarks'] ?? null) : null;

            return SendResult::failed(is_string($remarks) ? $remarks : 'http_'.$response->status());
        }

        return SendResult::sent(is_scalar($id) ? (string) $id : null);
    }
}
