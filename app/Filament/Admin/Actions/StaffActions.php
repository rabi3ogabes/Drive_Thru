<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Domain\Access\Actions\InviteUser;
use App\Domain\Security\LoginThrottle;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Actions on a platform team member. Policies keep anyone from acting on
 * themselves, and only a super admin from acting on another super admin.
 */
final class StaffActions
{
    public static function resendInvitation(): Action
    {
        return Action::make('resendInvitation')
            ->label(__('clients.actions.resend_invitation'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->last_login_at === null && $record->is_active && Gate::allows('update', $record))
            ->action(function (User $record): void {
                /** @var User $admin */
                $admin = auth()->user();
                $url = app(InviteUser::class)->handle($record, $admin, context: 'platform');

                ClientActions::notifyInvitationLink($url, $record->phone, config('app.name'));
            });
    }

    public static function unlockSignIn(): Action
    {
        return Action::make('unlockSignIn')
            ->label(__('team.actions.unlock'))
            ->icon(Heroicon::OutlinedLockOpen)
            ->requiresConfirmation()
            ->modalDescription(__('team.actions.unlock_description'))
            ->visible(fn (User $record): bool => Gate::allows('update', $record))
            ->action(function (User $record): void {
                $unlocked = app(LoginThrottle::class)->unlockEmail($record->email);

                activity()->performedOn($record)->causedBy(auth()->user())->event('security.unlocked')->log('Sign-in unlocked');

                Notification::make()
                    ->success()
                    ->title($unlocked ? __('team.notifications.unlocked') : __('team.notifications.not_locked'))
                    ->send();
            });
    }

    public static function resetTwoFactor(): Action
    {
        return Action::make('resetTwoFactor')
            ->label(__('team.actions.reset_two_factor'))
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('team.actions.reset_two_factor_description'))
            ->visible(fn (User $record): bool => filled($record->app_authentication_secret) && Gate::allows('update', $record))
            ->action(function (User $record): void {
                $record->saveAppAuthenticationSecret(null);
                $record->saveAppAuthenticationRecoveryCodes(null);

                activity()->performedOn($record)->causedBy(auth()->user())->event('security.two_factor_reset')->log('Two-factor reset');

                Notification::make()->success()->title(__('team.notifications.two_factor_reset'))->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspendStaff')
            ->label(__('team.actions.suspend'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('team.actions.suspend_description'))
            ->visible(fn (User $record): bool => $record->is_active && Gate::allows('suspend', $record))
            ->action(function (User $record): void {
                $record->forceFill(['is_active' => false])->save();

                // End every open session of the suspended person immediately.
                DB::table(config('session.table', 'sessions'))->where('user_id', $record->id)->delete();

                Notification::make()->success()->title(__('team.notifications.suspended', ['name' => $record->name]))->send();
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivateStaff')
            ->label(__('team.actions.reactivate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => ! $record->is_active && Gate::allows('suspend', $record))
            ->action(function (User $record): void {
                $record->forceFill(['is_active' => true])->save();

                Notification::make()->success()->title(__('team.notifications.reactivated', ['name' => $record->name]))->send();
            });
    }
}
