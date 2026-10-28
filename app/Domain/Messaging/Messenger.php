<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\Messaging\Channels\LogChannel;
use App\Domain\Messaging\Channels\SmsAlaChannel;
use App\Domain\Messaging\Channels\WhatsAppCloudChannel;
use App\Domain\Messaging\Contracts\MessageChannel;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Platform\PlatformSettings;
use Throwable;

/**
 * Sends a message through the platform's channels in order (WhatsApp first,
 * SMS as the fallback by default). Each attempt is recorded as a delivery;
 * the first channel that takes the message wins. Outside production, a
 * channel without credentials logs instead of sending.
 */
final class Messenger
{
    /** @var array<string, MessageChannel> */
    private array $channels = [];

    public function __construct(
        private readonly PlatformSettings $settings,
        WhatsAppCloudChannel $whatsapp,
        SmsAlaChannel $sms,
    ) {
        $this->use($whatsapp);
        $this->use($sms);
    }

    public function use(MessageChannel $channel): void
    {
        $this->channels[$channel->name()] = $channel;
    }

    /** The delivery that went out, or null if no channel could take the message. */
    public function send(Message $message): ?MessageDelivery
    {
        $order = $message->channels === null ? $this->order() : array_values(array_intersect($this->order(), $message->channels));

        foreach ($order as $name) {
            $channel = $this->resolve($name);

            if ($channel === null) {
                continue;
            }

            $delivery = MessageDelivery::withoutTenancy()->create([
                'restaurant_id' => $message->restaurantId,
                'channel' => $name,
                'purpose' => $message->purpose,
                'to_hash' => $message->phoneHash(),
                'to_masked' => $message->maskedPhone(),
                'status' => MessageDelivery::QUEUED,
                'subject_type' => $message->subject?->getMorphClass() ?? '',
                'subject_id' => $message->subject?->getKey() ?? 0,
            ]);

            try {
                $result = $channel->send($message, $delivery);
            } catch (Throwable $e) {
                report($e);
                $delivery->forceFill(['status' => MessageDelivery::FAILED, 'error' => 'exception'])->save();

                continue;
            }

            if ($result->sent) {
                $delivery->forceFill(['status' => MessageDelivery::SENT, 'provider_message_id' => $result->providerMessageId])->save();

                return $delivery;
            }

            $delivery->forceFill(['status' => MessageDelivery::FAILED, 'error' => mb_substr((string) $result->error, 0, 280)])->save();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function order(): array
    {
        $configured = $this->settings->get('otp.channels');
        $order = is_array($configured) ? array_values(array_filter($configured, 'is_string')) : [];

        return $order !== [] ? $order : [WhatsAppCloudChannel::NAME, SmsAlaChannel::NAME];
    }

    private function resolve(string $name): ?MessageChannel
    {
        $channel = $this->channels[$name] ?? null;

        if ($channel !== null && $channel->available()) {
            return $channel;
        }

        return config('messaging.log_when_unconfigured') ? new LogChannel($name) : null;
    }
}
