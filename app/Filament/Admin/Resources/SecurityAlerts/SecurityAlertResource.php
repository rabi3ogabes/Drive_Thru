<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SecurityAlerts;

use App\Domain\Security\LoginThrottle;
use App\Domain\Security\Models\SecurityAlert;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\SecurityAlerts\Pages\ListSecurityAlerts;
use App\Support\UserAgent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class SecurityAlertResource extends Resource
{
    protected static ?string $model = SecurityAlert::class;

    protected static ?string $slug = 'security/alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Security;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('security.center.alert');
    }

    public static function getPluralModelLabel(): string
    {
        return __('security.center.alerts');
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = SecurityAlert::unread()->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('security.center.what'))
                    ->badge()
                    ->color('danger')
                    ->icon('heroicon-m-lock-closed'),
                TextColumn::make('attempted_email')->label(__('security.center.account'))->placeholder(__('security.center.network'))->fontFamily('mono'),
                TextColumn::make('attempt_count')->label(__('security.center.attempts'))->numeric()->alignCenter(),
                TextColumn::make('details.user_agent')
                    ->label(__('security.center.device'))
                    ->formatStateUsing(fn (?string $state): string => ($d = UserAgent::describe($state))['browser'].' · '.$d['platform'])
                    ->toggleable(),
                TextColumn::make('lock_state')
                    ->label(__('security.center.lock'))
                    ->state(fn (SecurityAlert $record): string => self::isStillLocked($record) ? __('security.center.locked') : __('security.center.lifted'))
                    ->badge()
                    ->color(fn (SecurityAlert $record): string => self::isStillLocked($record) ? 'warning' : 'gray'),
                TextColumn::make('created_at')->label(__('audit.when'))->since()->sortable(),
                TextColumn::make('read_at')
                    ->label('')
                    ->state(fn (SecurityAlert $record): string => $record->read_at === null ? __('security.center.new') : '')
                    ->badge()
                    ->color('danger'),
            ])
            ->filters([
                TernaryFilter::make('read_at')
                    ->label(__('security.center.read_state'))
                    ->nullable()
                    ->trueLabel(__('security.center.read'))
                    ->falseLabel(__('security.center.unread')),
            ])
            ->recordActions([
                Action::make('unlock')
                    ->label(__('team.actions.unlock'))
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SecurityAlert $record): bool => self::isStillLocked($record))
                    ->action(function (SecurityAlert $record): void {
                        app(LoginThrottle::class)->unlock((string) ($record->details['principal'] ?? ''));
                        $record->update(['read_at' => $record->read_at ?? now()]);
                        activity()->performedOn($record)->causedBy(auth()->user())->event('security.unlocked')->log('Sign-in unlocked');

                        Notification::make()->success()->title(__('team.notifications.unlocked'))->send();
                    }),
                Action::make('markRead')
                    ->label(__('security.center.mark_read'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->visible(fn (SecurityAlert $record): bool => $record->read_at === null)
                    ->action(fn (SecurityAlert $record) => $record->update(['read_at' => now()])),
            ])
            ->toolbarActions([
                BulkAction::make('markAllRead')
                    ->label(__('security.center.mark_read'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Collection $records) => SecurityAlert::query()->whereKey($records->modelKeys())->whereNull('read_at')->update(['read_at' => now()]))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading(__('security.center.empty'))
            ->emptyStateDescription(__('security.center.empty_hint'))
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    private static function isStillLocked(SecurityAlert $alert): bool
    {
        $principal = $alert->details['principal'] ?? null;

        return is_string($principal) && app(LoginThrottle::class)->isLocked($principal);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityAlerts::route('/'),
        ];
    }
}
