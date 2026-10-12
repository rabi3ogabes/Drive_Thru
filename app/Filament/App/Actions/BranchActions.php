<?php

declare(strict_types=1);

namespace App\Filament\App\Actions;

use App\Domain\Branches\Actions\UpdateOrderingStatus;
use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Enums\BranchState;
use App\Domain\Branches\Links;
use App\Domain\Branches\Models\Branch;
use App\Filament\App\Resources\Branches\Schemas\BranchForm;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * The live controls a manager reaches for mid-shift: pause, busy mode,
 * resume, hours, and switching a branch off.
 */
final class BranchActions
{
    public static function live(): ActionGroup
    {
        return ActionGroup::make([self::pause(), self::busy(), self::resume()])
            ->label(__('branches.actions.live'))
            ->dropdownPlacement('bottom-start')
            ->icon('heroicon-m-bolt')
            ->button()
            ->color('gray')
            ->size('sm');
    }

    public static function pause(): Action
    {
        return Action::make('pause')
            ->label(__('branches.actions.pause'))
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->modalIcon('heroicon-o-pause-circle')
            ->modalHeading(fn (Branch $record): string => __('branches.pause.heading', ['branch' => $record->name]))
            ->modalDescription(__('branches.pause.description'))
            ->modalWidth(Width::Medium)
            ->schema([
                ToggleButtons::make('minutes')
                    ->hiddenLabel()
                    ->options([
                        '15' => __('branches.pause.minutes', ['count' => 15]),
                        '30' => __('branches.pause.minutes', ['count' => 30]),
                        '60' => __('branches.pause.hour'),
                        'close' => __('branches.pause.until_close'),
                    ])
                    ->default('30')
                    ->inline()
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('branches.actions.pause'))
            ->authorize(fn (Branch $record): bool => auth()->user()?->can('operate', $record) ?? false)
            ->visible(fn (Branch $record): bool => app(BranchSchedule::class)->state($record) !== BranchState::Paused)
            ->action(function (Branch $record, array $data): void {
                app(UpdateOrderingStatus::class)->pause($record, $data['minutes'] === 'close' ? null : (int) $data['minutes'], Workspace::user());

                Notification::make()->success()
                    ->title(__('branches.notifications.paused', ['branch' => $record->name]))
                    ->body(__('branches.notifications.paused_until', ['time' => $record->paused_until?->setTimezone(Workspace::restaurant()->timezone)->format('H:i')]))
                    ->send();
            });
    }

    public static function busy(): Action
    {
        return Action::make('busy')
            ->label(__('branches.actions.busy'))
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->modalIcon('heroicon-o-clock')
            ->modalHeading(__('branches.busy.heading'))
            ->modalDescription(__('branches.busy.description'))
            ->modalWidth(Width::Medium)
            ->schema([
                ToggleButtons::make('extra')
                    ->label(__('branches.busy.extra'))
                    ->options(collect([5, 10, 15, 20])->mapWithKeys(fn (int $m): array => [(string) $m => '+'.__('branches.pause.minutes', ['count' => $m])])->all())
                    ->default('10')
                    ->inline()
                    ->required(),
                ToggleButtons::make('for')
                    ->label(__('branches.busy.for'))
                    ->options([
                        '30' => __('branches.pause.minutes', ['count' => 30]),
                        '60' => __('branches.pause.hour'),
                        '120' => __('branches.pause.hours', ['count' => 2]),
                    ])
                    ->default('60')
                    ->inline()
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('branches.actions.busy'))
            ->authorize(fn (Branch $record): bool => auth()->user()?->can('operate', $record) ?? false)
            ->visible(fn (Branch $record): bool => app(BranchSchedule::class)->state($record) === BranchState::Open)
            ->action(function (Branch $record, array $data): void {
                app(UpdateOrderingStatus::class)->busy($record, (int) $data['extra'], (int) $data['for'], Workspace::user());

                Notification::make()->success()
                    ->title(__('branches.notifications.busy', ['minutes' => (int) $data['extra']]))
                    ->send();
            });
    }

    public static function resume(): Action
    {
        return Action::make('resume')
            ->label(__('branches.actions.resume'))
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-play-circle')
            ->modalHeading(fn (Branch $record): string => __('branches.resume.heading', ['branch' => $record->name]))
            ->modalDescription(__('branches.resume.description'))
            ->authorize(fn (Branch $record): bool => auth()->user()?->can('operate', $record) ?? false)
            ->visible(fn (Branch $record): bool => $record->isPaused() || $record->isBusy())
            ->action(function (Branch $record): void {
                app(UpdateOrderingStatus::class)->resume($record, Workspace::user());

                Notification::make()->success()->title(__('branches.notifications.resumed', ['branch' => $record->name]))->send();
            });
    }

    /** Hours and closures for branch managers, who can't edit the rest of the branch. */
    public static function hours(): EditAction
    {
        return EditAction::make('hours')
            ->label(__('branches.actions.hours'))
            ->icon('heroicon-o-calendar-days')
            ->modalHeading(fn (Branch $record): string => __('branches.hours_heading', ['branch' => $record->name]))
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->schema(BranchForm::hours())
            ->authorize(fn (Branch $record): bool => auth()->user()?->can('operate', $record) ?? false)
            ->visible(fn (Branch $record): bool => ! (auth()->user()?->can('update', $record) ?? false) && (auth()->user()?->can('operate', $record) ?? false))
            ->successNotificationTitle(__('branches.notifications.hours_saved'));
    }

    public static function switchOpen(): Action
    {
        return Action::make('switchOpen')
            ->label(fn (Branch $record): string => $record->is_open ? __('branches.actions.switch_off') : __('branches.actions.switch_on'))
            ->icon(fn (Branch $record): string => $record->is_open ? 'heroicon-o-no-symbol' : 'heroicon-o-power')
            ->color(fn (Branch $record): string => $record->is_open ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading(fn (Branch $record): string => $record->is_open ? __('branches.switch.off_heading', ['branch' => $record->name]) : __('branches.switch.on_heading', ['branch' => $record->name]))
            ->modalDescription(fn (Branch $record): string => $record->is_open ? __('branches.switch.off_description') : __('branches.switch.on_description'))
            ->authorize(fn (Branch $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Branch $record): void {
                $record->forceFill(['is_open' => ! $record->is_open])->save();

                Notification::make()->success()
                    ->title($record->is_open ? __('branches.notifications.switched_on') : __('branches.notifications.switched_off'))
                    ->send();
            });
    }

    public static function openPublicPage(): Action
    {
        return Action::make('publicPage')
            ->label(__('branches.actions.public_page'))
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->url(fn (Branch $record): string => app(Links::class)->branch(Workspace::restaurant(), $record), shouldOpenInNewTab: true);
    }
}
