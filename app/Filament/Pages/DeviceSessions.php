<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Security\Data\DeviceSession;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Devices & sessions": every browser signed in to this account, with the
 * ability to sign one out or all others (password confirmed).
 */
class DeviceSessions extends Page
{
    protected string $view = 'filament.pages.device-sessions';

    protected static ?string $slug = 'account/sessions';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string|Htmlable
    {
        return __('security.sessions.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('security.sessions.subheading');
    }

    public function usesDatabaseSessions(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return Collection<int, DeviceSession>
     */
    public function getSessions(): Collection
    {
        if (! $this->usesDatabaseSessions()) {
            return collect();
        }

        $currentId = session()->getId();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row): DeviceSession => DeviceSession::fromRow($row, $currentId))
            ->values();
    }

    public function signOutSessionAction(): Action
    {
        return Action::make('signOutSession')
            ->label(__('security.sessions.sign_out'))
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('gray')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('security.sessions.sign_out_confirm_heading'))
            ->modalDescription(__('security.sessions.sign_out_confirm_body'))
            ->action(function (array $arguments): void {
                $sessionId = (string) ($arguments['session'] ?? '');

                if ($sessionId === '' || hash_equals(session()->getId(), $sessionId)) {
                    return;
                }

                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', auth()->id())
                    ->where('id', $sessionId)
                    ->delete();

                Notification::make()->title(__('security.sessions.signed_out'))->success()->send();
            });
    }

    public function signOutOthersAction(): Action
    {
        return Action::make('signOutOthers')
            ->label(__('security.sessions.sign_out_others'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('danger')
            ->modalHeading(__('security.sessions.sign_out_others'))
            ->modalDescription(__('security.sessions.sign_out_others_body'))
            ->modalSubmitActionLabel(__('security.sessions.sign_out_others_submit'))
            ->schema([
                TextInput::make('password')
                    ->label(__('security.sessions.password'))
                    ->password()
                    ->revealable()
                    ->currentPassword()
                    ->required(),
            ])
            ->visible(fn (): bool => $this->usesDatabaseSessions() && $this->getSessions()->where('current', false)->isNotEmpty())
            ->action(function (): void {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', auth()->id())
                    ->where('id', '!=', session()->getId())
                    ->delete();

                activity()->causedBy(auth()->user())->event('sessions.revoked')->log('Signed out all other devices');

                Notification::make()->title(__('security.sessions.signed_out_others'))->success()->send();
            });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->signOutOthersAction()];
    }
}
