<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Alerts\OpsAlert;
use App\Domain\Notifications\Push\WebPushSender;
use App\Models\User;

/** Laravel notification channel: web push to every browser the person subscribed. */
final class WebPushChannel
{
    public function __construct(private readonly WebPushSender $sender) {}

    public function send(User $notifiable, OpsAlert $notification): void
    {
        $this->sender->send($notifiable, $notification->toWebPush($notifiable), $notification->restaurantId);
    }
}
