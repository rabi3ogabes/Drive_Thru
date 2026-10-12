<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Messaging\Messenger;
use App\Domain\Notifications\Alerts\OpsAlert;
use App\Models\User;

/** Laravel notification channel: the alert on WhatsApp (SMS as the fallback) for managers who opted in. */
final class WhatsAppAlertChannel
{
    public function __construct(private readonly Messenger $messenger) {}

    public function send(User $notifiable, OpsAlert $notification): void
    {
        $this->messenger->send($notification->toWhatsApp($notifiable));
    }
}
