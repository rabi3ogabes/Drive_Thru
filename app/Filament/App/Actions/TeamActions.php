<?php

declare(strict_types=1);

namespace App\Filament\App\Actions;

use App\Domain\Access\Actions\InviteUser;
use App\Domain\Staff\Actions\ChangeEmployeeStatus;
use App\Domain\Staff\Actions\SetEmployeePin;
use App\Domain\Staff\Actions\UpdateEmployee;
use App\Domain\Staff\Data\EmployeeData;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Filament\Admin\Actions\ClientActions;
use App\Filament\App\Resources\Team\Schemas\EmployeeForm;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Everything an owner or manager does to a team member, each guarded by the
 * same rules as the domain actions (never more power than you hold).
 */
final class TeamActions
{
    public static function edit(): EditAction
    {
        return EditAction::make()
            ->modalWidth(Width::TwoExtraLarge)
            ->schema(EmployeeForm::schema(editing: true))
            ->fillForm(fn (Employee $record): array => [
                'name' => $record->user->name,
                'email' => $record->user->email,
                'phone' => $record->user->phone,
                'position_id' => $record->position_id,
                'all_branches' => $record->all_branches,
                'branch_ids' => $record->branches->modelKeys(),
            ])
            ->using(function (Employee $record, array $data, EditAction $action): Employee {
                try {
                    return app(UpdateEmployee::class)->handle($record, EmployeeData::fromArray($data + ['email' => $record->user->email]), Workspace::user());
                } catch (AuthorizationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    throw new Halt;
                }
            })
            ->successNotificationTitle(__('staff.notifications.updated'));
    }

    public static function sendLink(): Action
    {
        return Action::make('sendLink')
            ->label(fn (Employee $record): string => $record->status === EmployeeStatus::Invited ? __('staff.actions.resend_invite') : __('staff.actions.reset_password'))
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-paper-airplane')
            ->modalHeading(fn (Employee $record): string => $record->status === EmployeeStatus::Invited
                ? __('staff.send_link.invite_heading', ['name' => $record->user->name])
                : __('staff.send_link.reset_heading', ['name' => $record->user->name]))
            ->modalDescription(__('staff.send_link.description'))
            ->authorize(fn (Employee $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->visible(fn (Employee $record): bool => $record->status !== EmployeeStatus::Suspended)
            ->action(function (Employee $record): void {
                $url = app(InviteUser::class)->handle($record->user, Workspace::user(), context: Workspace::restaurant()->name);

                ClientActions::notifyInvitationLink($url, $record->user->phone, Workspace::restaurant()->name);
            });
    }

    public static function setPin(): Action
    {
        return Action::make('setPin')
            ->label(fn (Employee $record): string => $record->hasPin() ? __('staff.actions.change_pin') : __('staff.actions.set_pin'))
            ->icon('heroicon-o-key')
            ->modalIcon('heroicon-o-key')
            ->modalHeading(fn (Employee $record): string => __('staff.pin.heading', ['name' => $record->user->name]))
            ->modalDescription(__('staff.pin.description'))
            ->modalWidth(Width::Medium)
            ->schema([
                TextInput::make('pin')
                    ->label(__('staff.pin.label'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(4)
                    ->maxLength(6)
                    ->regex('/^\d{4,6}$/')
                    ->autocomplete('off')
                    ->extraInputAttributes(['inputmode' => 'numeric', 'dir' => 'ltr']),
            ])
            ->extraModalFooterActions(fn (Employee $record): array => $record->hasPin() ? [
                Action::make('clearPin')
                    ->label(__('staff.actions.clear_pin'))
                    ->color('danger')
                    ->link()
                    ->cancelParentActions()
                    ->action(function () use ($record): void {
                        app(SetEmployeePin::class)->handle($record, null, Workspace::user());
                        Notification::make()->success()->title(__('staff.notifications.pin_cleared'))->send();
                    }),
            ] : [])
            ->authorize(fn (Employee $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Employee $record, array $data, Action $action): void {
                try {
                    app(SetEmployeePin::class)->handle($record, (string) $data['pin'], Workspace::user());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title($e->validator->errors()->first('pin'))->send();
                    throw new Halt;
                }

                Notification::make()->success()->title(__('staff.notifications.pin_set', ['name' => $record->user->name]))->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label(__('staff.actions.suspend'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Employee $record): string => __('staff.suspend.heading', ['name' => $record->user->name]))
            ->modalDescription(__('staff.suspend.description'))
            ->authorize(fn (Employee $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->visible(fn (Employee $record): bool => $record->status !== EmployeeStatus::Suspended)
            ->action(function (Employee $record): void {
                app(ChangeEmployeeStatus::class)->suspend($record, Workspace::user());
                Notification::make()->success()->title(__('staff.notifications.suspended', ['name' => $record->user->name]))->send();
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label(__('staff.actions.reactivate'))
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->requiresConfirmation()
            ->authorize(fn (Employee $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->visible(fn (Employee $record): bool => $record->status === EmployeeStatus::Suspended)
            ->action(function (Employee $record): void {
                app(ChangeEmployeeStatus::class)->reactivate($record, Workspace::user());
                Notification::make()->success()->title(__('staff.notifications.reactivated', ['name' => $record->user->name]))->send();
            });
    }

    public static function remove(): Action
    {
        return Action::make('remove')
            ->label(__('staff.actions.remove'))
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-user-minus')
            ->modalHeading(fn (Employee $record): string => __('staff.remove.heading', ['name' => $record->user->name]))
            ->modalDescription(__('staff.remove.description'))
            ->modalSubmitActionLabel(__('staff.actions.remove'))
            ->authorize(fn (Employee $record): bool => auth()->user()?->can('delete', $record) ?? false)
            ->action(function (Employee $record): void {
                $name = $record->user->name;
                app(ChangeEmployeeStatus::class)->remove($record, Workspace::user());
                Notification::make()->success()->title(__('staff.notifications.removed', ['name' => $name]))->send();
            });
    }
}
