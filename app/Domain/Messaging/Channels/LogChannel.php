<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channels;

use App\Domain\Messaging\Contracts\MessageChannel;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Data\SendResult;
use App\Domain\Messaging\Models\MessageDelivery;
use Illuminate\Support\Facades\Log;

/** Development stand-in: records that a message would go out. Never logs the text. */
final class LogChannel implements MessageChannel
{
    public function __construct(private readonly string $channel) {}

    public function name(): string
    {
        return $this->channel;
    }

    public function available(): bool
    {
        return true;
    }

    public function send(Message $message, MessageDelivery $delivery): SendResult
    {
        Log::info("Message queued via {$this->channel}", ['to' => $message->maskedPhone(), 'purpose' => $message->purpose, 'length' => mb_strlen($message->text)]);

        return SendResult::sent('log-'.$delivery->id);
    }
}
