<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Data\SendResult;
use App\Domain\Messaging\Models\MessageDelivery;

/** A way to reach a phone (WhatsApp, SMS). */
interface MessageChannel
{
    /** whatsapp | sms */
    public function name(): string;

    /** Whether the channel has what it needs to send. */
    public function available(): bool;

    /** Hands the message to the provider; the delivery row lets the provider report back. */
    public function send(Message $message, MessageDelivery $delivery): SendResult;
}
