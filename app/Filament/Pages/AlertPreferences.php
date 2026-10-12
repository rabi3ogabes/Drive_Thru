<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * "Alerts": how this person hears about late orders, waiting guests and
 * failed refunds. The bell is always on; push (per device), email for
 * critical alerts and WhatsApp are their choice. Each change saves at once.
 */
class AlertPreferences extends Page
{
    public const CHANNELS = ['push', 'email', 'whatsapp'];

    protected string $view = 'filament.pages.alert-preferences';

    protected static ?string $slug = 'account/alerts';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, bool> */
    public array $prefs = [];

    public function mount(): void
    {
        $user = $this->user();

        foreach (self::CHANNELS as $channel) {
            $this->prefs[$channel] = $user->wantsAlertsBy($channel);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('alerts.preferences.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('alerts.preferences.subheading');
    }

    public function hasPhone(): bool
    {
        return filled($this->user()->phone);
    }

    public function toggle(string $channel): void
    {
        abort_unless(in_array($channel, self::CHANNELS, true), 422);

        if ($channel === 'whatsapp' && ! $this->hasPhone() && ! ($this->prefs['whatsapp'] ?? false)) {
            Notification::make()->warning()->title(__('alerts.preferences.whatsapp_needs_phone'))->send();

            return;
        }

        $this->prefs[$channel] = ! ($this->prefs[$channel] ?? false);

        $user = $this->user();
        $user->forceFill(['notification_prefs' => array_merge(is_array($user->notification_prefs) ? $user->notification_prefs : [], [$channel => $this->prefs[$channel]])])->save();

        Notification::make()->success()->title(__('alerts.preferences.saved'))->duration(2500)->send();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
