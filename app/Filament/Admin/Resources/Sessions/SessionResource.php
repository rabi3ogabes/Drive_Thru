<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sessions;

use App\Domain\Security\Models\SessionRecord;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\Sessions\Pages\ListSessions;
use App\Support\UserAgent;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SessionResource extends Resource
{
    protected static ?string $model = SessionRecord::class;

    protected static ?string $slug = 'security/sessions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Security;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('security.center.session');
    }

    public static function getPluralModelLabel(): string
    {
        return __('security.center.sessions');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'])
            ->whereNotNull('user_id')
            ->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity', 'desc')
            ->poll('60s')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('security.center.person'))
                    ->description(fn (SessionRecord $record): ?string => $record->user?->email)
                    ->weight('medium')
                    ->searchable(['name', 'email']),
                TextColumn::make('user_agent')
                    ->label(__('security.center.device'))
                    ->formatStateUsing(fn (?string $state): string => ($d = UserAgent::describe($state))['browser'].' · '.$d['platform']),
                TextColumn::make('ip_address')->label('IP')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('last_activity')
                    ->label(__('security.center.last_active'))
                    ->formatStateUsing(fn (SessionRecord $record): string => $record->lastActiveAt()->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('platform')
                    ->label(__('security.center.platform_only'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('is_super_admin', true)->orWhereNotNull('platform_role_id')),
                        false: fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('is_super_admin', false)->whereNull('platform_role_id')),
                    ),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label(__('security.sessions.sign_out'))
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->modalHeading(__('security.sessions.sign_out_confirm_heading'))
                    ->modalDescription(__('security.sessions.sign_out_confirm_body'))
                    ->successNotificationTitle(__('security.sessions.signed_out')),
            ])
            ->emptyStateHeading(__('security.center.no_sessions'))
            ->emptyStateIcon('heroicon-o-computer-desktop');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSessions::route('/'),
        ];
    }
}
