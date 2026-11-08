<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SupportTickets;

use App\Domain\Services\Enums\Service;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Models\SupportTicket;
use App\Filament\App\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\App\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Bidi;
use App\Support\Lang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Guest problem reports for the branches the person works in: open ones
 * first, the oldest flagged after 24 hours. Each opens to the conversation.
 */
class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $slug = 'support';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Operations;

    protected static ?int $navigationSort = 21;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasService(Service::DriveThru);
    }

    public static function getModelLabel(): string
    {
        return __('support.admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support.admin.plural');
    }

    /** Only the branches the person works in, for the list and for opening a ticket. */
    public static function getEloquentQuery(): Builder
    {
        return Workspace::scopeToBranches(parent::getEloquentQuery(), 'branch_id');
    }

    public static function getNavigationBadge(): ?string
    {
        $open = static::getEloquentQuery()->where('status', '!=', SupportTicket::RESOLVED)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        $tz = fn (): string => Workspace::restaurant()->timezone;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'branch', 'order'])
                ->orderByRaw('case when status = ? then 1 else 0 end', [SupportTicket::RESOLVED]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label(__('support.admin.columns.number'))->weight('semibold')
                    ->formatStateUsing(fn (SupportTicket $record): string => $record->displayNumber())
                    ->description(fn (SupportTicket $record): ?string => $record->isOverdue() ? Lang::text('support.admin.overdue') : null)
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('category')->label(__('support.admin.columns.category'))->badge()->color('gray')
                    ->icon(fn (TicketCategory $state): string => $state->icon()),
                TextColumn::make('customer.name')->label(__('support.admin.columns.guest'))
                    ->state(fn (SupportTicket $record): string => trim(($record->customer->name ?? '').' · '.(string) Bidi::ltr($record->customer->maskedPhone()), ' ·'))
                    ->description(fn (SupportTicket $record): string => $record->order->displayNumber()),
                TextColumn::make('branch.name')->label(__('support.admin.columns.branch'))->toggleable(),
                TextColumn::make('status')->label(__('support.admin.columns.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => Lang::text('support.admin.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        SupportTicket::OPEN => 'warning',
                        SupportTicket::ANSWERED => 'info',
                        default => 'success',
                    }),
                TextColumn::make('created_at')->label(__('support.admin.columns.opened'))->since()->timezone($tz)
                    ->color(fn (SupportTicket $record): ?string => $record->isOverdue() ? 'danger' : null)->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('support.admin.columns.status'))
                    ->options(collect(SupportTicket::STATUSES)->mapWithKeys(fn (string $status): array => [$status => Lang::text('support.admin.status.'.$status)])->all()),
                SelectFilter::make('category')->label(__('support.admin.columns.category'))->options(TicketCategory::class),
                SelectFilter::make('branch_id')->label(__('support.admin.columns.branch'))->options(fn (): array => Workspace::branchOptions()),
            ])
            ->recordUrl(fn (SupportTicket $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedLifebuoy)
            ->emptyStateHeading(__('support.admin.empty.heading'))
            ->emptyStateDescription(__('support.admin.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'view' => ViewSupportTicket::route('/{record}'),
        ];
    }
}
