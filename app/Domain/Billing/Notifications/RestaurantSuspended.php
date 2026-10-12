<?php

declare(strict_types=1);

namespace App\Domain\Billing\Notifications;

use App\Domain\Tenancy\Models\Restaurant;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RestaurantSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Restaurant $restaurant) {}

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
            ->subject(__('billing.mail.suspended.subject', ['restaurant' => $this->restaurant->name]))
            ->greeting(__('billing.mail.greeting'))
            ->line(__('billing.mail.suspended.body'))
            ->action(__('billing.mail.suspended.action'), url('/app/'.$this->restaurant->slug))
            ->salutation(__('billing.mail.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('billing.mail.suspended.subject', ['restaurant' => $this->restaurant->name]))
            ->body(__('billing.mail.suspended.body'))
            ->icon('heroicon-o-pause-circle')
            ->danger()
            ->getDatabaseMessage();
    }
}
