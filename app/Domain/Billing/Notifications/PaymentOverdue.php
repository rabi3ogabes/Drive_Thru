<?php

declare(strict_types=1);

namespace App\Domain\Billing\Notifications;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Subscription $subscription,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('billing.mail.overdue.subject', ['number' => $this->invoice->number]))
            ->greeting(__('billing.mail.greeting'))
            ->line(__('billing.mail.overdue.body', [
                'number' => $this->invoice->number,
                'total' => $this->invoice->formattedTotal(),
                'date' => $this->subscription->grace_ends_at?->timezone(config('app.display_timezone'))->format('Y-m-d') ?? '—',
            ]))
            ->salutation(__('billing.mail.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('billing.mail.overdue.subject', ['number' => $this->invoice->number]))
            ->body($this->invoice->formattedTotal())
            ->icon('heroicon-o-clock')
            ->danger()
            ->getDatabaseMessage();
    }
}
