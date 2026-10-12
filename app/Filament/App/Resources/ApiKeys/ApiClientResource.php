<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ApiKeys;

use App\Domain\Integrations\Actions\IssueApiClient;
use App\Domain\Integrations\Api\ApiScope;
use App\Domain\Integrations\Models\ApiClient;
use App\Filament\App\Resources\ApiKeys\Pages\ManageApiKeys;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ApiClientResource extends Resource
{
    protected static ?string $model = ApiClient::class;

    protected static ?string $slug = 'api-keys';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Restaurant;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('integrations.api.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('integrations.api.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('integrations.api.navigation');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('integrations.api.fields.name'))
                    ->weight('semibold')
                    ->description(fn (ApiClient $record): string => __('integrations.api.created_by', [
                        'name' => $record->creator->name ?? '—',
                        'date' => $record->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d') ?? '',
                    ]))
                    ->searchable(),
                TextColumn::make('scopes')
                    ->label(__('integrations.api.fields.scopes'))
                    ->formatStateUsing(fn (string $state): string => ApiScope::tryFrom($state)?->getLabel() ?? $state)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('integrations.api.fields.status'))
                    ->state(fn (ApiClient $record): string => $record->isActive() ? 'active' : 'revoked')
                    ->formatStateUsing(fn (string $state): string => __('integrations.api.status.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('last_used_at')
                    ->label(__('integrations.api.fields.last_used'))
                    ->since()
                    ->placeholder(__('integrations.api.never')),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('integrations.api.fields.status'))
                    ->default(true)
                    ->trueLabel(__('integrations.api.status.active'))
                    ->falseLabel(__('integrations.api.status.revoked'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('revoked_at'),
                        false: fn (Builder $query) => $query->whereNotNull('revoked_at'),
                    ),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('integrations.api.actions.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ApiClient $record): string => __('integrations.api.revoke.heading', ['name' => $record->name]))
                    ->modalDescription(__('integrations.api.revoke.description'))
                    ->visible(fn (ApiClient $record): bool => $record->isActive())
                    ->authorize(fn (ApiClient $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->action(function (ApiClient $record): void {
                        app(IssueApiClient::class)->revoke($record, Workspace::user());
                        Notification::make()->success()->title(__('integrations.api.notifications.revoked', ['name' => $record->name]))->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading(__('integrations.api.empty.heading'))
            ->emptyStateDescription(__('integrations.api.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApiKeys::route('/'),
        ];
    }
}
