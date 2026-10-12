<?php

declare(strict_types=1);

namespace App\Domain\Growth\Notifications;

use App\Domain\Growth\Models\Lead;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Bell notification for the sales team when a restaurant asks to join.
 */
final class NewLeadReceived extends Notification
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('leads.notifications.new.title', ['restaurant' => $this->lead->restaurant_name]))
            ->body(__('leads.notifications.new.body', [
                'contact' => $this->lead->contact_name,
                'branches' => $this->lead->branches_count,
            ]))
            ->icon('heroicon-o-sparkles')
            ->success()
            ->actions([
                Action::make('open')
                    ->label(__('leads.notifications.new.action'))
                    ->url(url("/admin/leads/{$this->lead->id}"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
