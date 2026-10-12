<?php

declare(strict_types=1);

namespace App\Domain\Growth\Notifications;

use App\Domain\Growth\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We received your request" email to the prospect, in their language.
 */
final class LeadAcknowledgement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('leads.mail.ack.subject'))
            ->greeting(__('leads.mail.ack.greeting', ['name' => $this->lead->contact_name]))
            ->line(__('leads.mail.ack.body', ['restaurant' => $this->lead->restaurant_name]))
            ->line(__('leads.mail.ack.next'))
            ->salutation(__('billing.mail.salutation'));
    }
}
