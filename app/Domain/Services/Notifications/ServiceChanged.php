<?php

declare(strict_types=1);

namespace App\Domain\Services\Notifications;

use App\Domain\Services\Models\RestaurantService;
use App\Domain\Services\ServicePricing;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\App\Pages\Billing;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner about a service: a demo started, a demo ends soon, a demo
 * ended or became paid, or the platform added a service. By email and in
 * the workspace bell.
 */
final class ServiceChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['demo_started', 'demo_ending', 'demo_ended', 'demo_converted', 'added'];

    public function __construct(
        public readonly Restaurant $restaurant,
        public readonly RestaurantService $row,
        public readonly string $kind,
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
            ->subject($this->subject())
            ->greeting(__('billing.mail.greeting'))
            ->line($this->line())
            ->action(__('services.notifications.action'), $this->url())
            ->salutation(__('billing.mail.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->subject())
            ->body($this->line())
            ->icon($this->row->service->getIcon())
            ->color(in_array($this->kind, ['demo_ending', 'demo_ended'], true) ? 'warning' : 'success')
            ->actions([Action::make('billing')->label(__('services.notifications.action'))->url($this->url())])
            ->getDatabaseMessage();
    }

    private function subject(): string
    {
        return __("services.notifications.{$this->kind}.subject", $this->replacements());
    }

    private function line(): string
    {
        return match ($this->kind) {
            'demo_ending' => __('services.notifications.demo_ending.'.($this->row->keep_after_demo ? 'line_keep' : 'line_end'), $this->replacements()),
            default => __("services.notifications.{$this->kind}.line", $this->replacements()),
        };
    }

    /**
     * @return array<string, string|int>
     */
    private function replacements(): array
    {
        $pricing = app(ServicePricing::class);
        $monthly = $this->row->unit_price_minor * $pricing->billableBranches($this->restaurant);

        return [
            'service' => $this->row->service->getLabel(),
            'restaurant' => $this->restaurant->name,
            'days' => $this->row->demoDaysLeft() ?? $pricing->demoDays($this->row->service),
            'monthly' => Money::format($monthly, $pricing->currency()),
        ];
    }

    private function url(): string
    {
        return Billing::getUrl(panel: 'app', tenant: $this->restaurant);
    }
}
