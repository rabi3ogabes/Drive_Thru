<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Ratings;

use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Feedback\RatingTags;
use App\Domain\Services\Enums\Service;
use App\Filament\App\Resources\Customers\CustomerResource;
use App\Filament\App\Resources\Ratings\Pages\ListRatings;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Bidi;
use App\Support\Lang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * What guests said after pickup: stars, tags and comments, per branch, with
 * the period's summary above the list. Read-only.
 */
class RatingResource extends Resource
{
    protected static ?string $model = OrderRating::class;

    protected static ?string $slug = 'ratings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Operations;

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasService(Service::DriveThru);
    }

    public static function getModelLabel(): string
    {
        return __('feedback.admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('feedback.admin.plural');
    }

    public static function table(Table $table): Table
    {
        $tz = fn (): string => Workspace::restaurant()->timezone;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => Workspace::scopeToBranches($query, 'branch_id')->with(['branch', 'customer', 'order']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('feedback.admin.columns.date'))->dateTime('d/m/Y H:i')->timezone($tz)->sortable(),
                TextColumn::make('stars')->label(__('feedback.admin.columns.stars'))->sortable()
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state): string => $state <= 2 ? 'danger' : ($state === 3 ? 'warning' : 'success'))
                    ->extraAttributes(['dir' => 'ltr', 'class' => 'tracking-wider']),
                TextColumn::make('tags')->label(__('feedback.admin.columns.tags'))->badge()
                    ->formatStateUsing(fn (string $state): string => Lang::text('feedback.tags.'.$state))
                    ->color(fn (string $state): string => in_array($state, RatingTags::POSITIVE, true) ? 'success' : 'gray')
                    ->placeholder('—'),
                TextColumn::make('comment')->label(__('feedback.admin.columns.comment'))->wrap()->limit(140)->placeholder('—'),
                TextColumn::make('branch.name')->label(__('feedback.admin.columns.branch'))->toggleable(),
                TextColumn::make('customer.name')->label(__('feedback.admin.columns.guest'))
                    ->state(fn (OrderRating $record): string => trim(($record->customer->name ?? '').' · '.(string) Bidi::ltr($record->customer->maskedPhone()), ' ·'))
                    ->description(fn (OrderRating $record): string => $record->order->displayNumber())
                    ->url(fn (OrderRating $record): ?string => CustomerResource::canAccess() ? CustomerResource::getUrl('view', ['record' => $record->customer]) : null),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label(__('feedback.admin.columns.branch'))->options(fn (): array => Workspace::branchOptions()),
                SelectFilter::make('stars')->label(__('feedback.admin.columns.stars'))
                    ->options(collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $n): array => [$n => trans_choice('feedback.card.stars', $n, ['count' => $n])])->all()),
                SelectFilter::make('tag')->label(__('feedback.admin.columns.tags'))
                    ->options(collect(RatingTags::all())->mapWithKeys(fn (string $tag): array => [$tag => Lang::text('feedback.tags.'.$tag)])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->whereJsonContains('tags', $data['value']) : $query),
                TernaryFilter::make('has_comment')->label(__('feedback.admin.filters.has_comment'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('comment'),
                        false: fn (Builder $query) => $query->whereNull('comment'),
                    ),
            ])
            ->emptyStateIcon(Heroicon::OutlinedStar)
            ->emptyStateHeading(__('feedback.admin.empty.heading'))
            ->emptyStateDescription(__('feedback.admin.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRatings::route('/'),
        ];
    }
}
