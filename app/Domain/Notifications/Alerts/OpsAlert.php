<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Alerts;

use App\Domain\Messaging\Data\Message;
use App\Domain\Notifications\Channels\WebPushChannel;
use App\Domain\Notifications\Channels\WhatsAppAlertChannel;
use App\Domain\Notifications\Push\WebPushSender;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An operations alert for the people running a branch. Always in the
 * panel's notification bell (with a chime, live); also as web push, on
 * WhatsApp for those who opted in, and by email when critical. Quiet hours
 * silence everything but the bell for non-critical alerts. Each person gets
 * it in their own language.
 */
final class OpsAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|int|array<string, string>>  $params  translation parameters; an array value is per locale (a branch name)
     */
    public function __construct(
        public readonly AlertKind $kind,
        public readonly int $restaurantId,
        public readonly array $params,
        public readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $restaurant = Restaurant::query()->find($this->restaurantId);
        $quiet = ! $this->kind->isCritical() && $restaurant?->inQuietHours() === true;
        $channels = ['database', 'broadcast'];

        if (! $quiet && $notifiable->wantsAlertsBy('push') && app(WebPushSender::class)->hasSubscribers($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        if (! $quiet && $notifiable->wantsAlertsBy('whatsapp') && filled($notifiable->phone)) {
            $channels[] = WhatsAppAlertChannel::class;
        }

        if ($this->kind->isCritical() && $notifiable->wantsAlertsBy('email') && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon($this->kind->icon())
            ->iconColor($this->kind->color())
            ->actions([Action::make('open')->label(__('alerts.open'))->url($this->url)->markAsRead()])
            ->getDatabaseMessage();
    }

    /** The event name Filament's notification bell already listens for, so it refreshes itself. */
    public function broadcastAs(): string
    {
        return 'database-notifications.sent';
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'kind' => $this->kind->value,
            'critical' => $this->kind->isCritical(),
            'title' => $this->title(),
            'url' => $this->url,
        ]);
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toWebPush(User $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => $this->url, 'tag' => 'alert-'.$this->kind->value];
    }

    public function toWhatsApp(User $notifiable): Message
    {
        return new Message(
            phone: (string) $notifiable->phone,
            purpose: Message::ALERT,
            text: $this->title().' — '.$this->body(),
            parameters: [$this->title(), $this->body()],
            locale: $notifiable->preferredLocale() ?? app()->getLocale(),
            restaurantId: $this->restaurantId,
        );
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject($this->title())
            ->greeting(__('alerts.mail.greeting', ['name' => $notifiable->name]))
            ->line($this->title())
            ->line($this->body())
            ->action(__('alerts.mail.action'), $this->url)
            ->salutation(__('alerts.mail.footer'));
    }

    public function title(): string
    {
        return __('alerts.'.$this->kind->value.'.title', $this->localized());
    }

    public function body(): string
    {
        return __('alerts.'.$this->kind->value.'.body', $this->localized());
    }

    /**
     * @return array<string, string|int>
     */
    private function localized(): array
    {
        $locale = app()->getLocale();

        return array_map(
            fn (string|int|array $value): string|int => is_array($value) ? ($value[$locale] ?? (string) (reset($value) ?: '')) : $value,
            $this->params,
        );
    }
}
