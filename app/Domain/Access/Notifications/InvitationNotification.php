<?php

declare(strict_types=1);

namespace App\Domain\Access\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  ?string  $context  "platform" for platform team invites, a restaurant name otherwise.
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $inviterName = null,
        public readonly ?string $context = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $where = $this->context === 'platform' || $this->context === null
            ? config('app.name')
            : $this->context;

        return (new MailMessage)
            ->subject(__('access.invitation.mail.subject', ['place' => $where]))
            ->greeting(__('access.invitation.mail.greeting'))
            ->line($this->inviterName !== null
                ? __('access.invitation.mail.body_with_inviter', ['inviter' => $this->inviterName, 'place' => $where])
                : __('access.invitation.mail.body', ['place' => $where]))
            ->action(__('access.invitation.mail.action'), $this->url)
            ->line(__('access.invitation.mail.expiry', ['days' => 7]))
            ->salutation(__('billing.mail.salutation'));
    }
}
