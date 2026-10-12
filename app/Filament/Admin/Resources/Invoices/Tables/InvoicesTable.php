<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Tenancy\Scopes\RestaurantScope;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table, bool $showClient = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScope(RestaurantScope::class)->with('restaurant'))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('billing.fields.number'))
                    ->weight('semibold')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('restaurant.name')
                    ->label(__('clients.label'))
                    ->visible($showClient)
                    ->url(fn (Invoice $record): ?string => $record->restaurant ? RestaurantResource::getUrl('view', ['record' => $record->restaurant]) : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('restaurant', fn (Builder $q) => $q
                        ->where('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%"))),
                TextColumn::make('period_start')
                    ->label(__('billing.fields.period'))
                    ->formatStateUsing(fn (Invoice $record): string => $record->period_start && $record->period_end
                        ? $record->period_start->format('Y-m-d').' → '.$record->period_end->format('Y-m-d')
                        : '—')
                    ->toggleable(),
                TextColumn::make('total_minor')
                    ->label(__('billing.fields.total'))
                    ->formatStateUsing(fn (Invoice $record): string => $record->formattedTotal())
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('clients.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (Invoice $record): string => $record->isOverdue() ? __('billing.overdue') : $record->status->getLabel())
                    ->color(fn (Invoice $record): string => $record->isOverdue() ? 'danger' : $record->status->getColor())
                    ->icon(fn (Invoice $record): string => $record->isOverdue() ? 'heroicon-m-exclamation-triangle' : $record->status->getIcon()),
                TextColumn::make('due_at')
                    ->label(__('billing.fields.due'))
                    ->date('Y-m-d')
                    ->sortable()
                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('paid_at')
                    ->label(__('billing.fields.paid_at'))
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('issued_at')
                    ->label(__('billing.fields.issued'))
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('clients.fields.status'))->options(InvoiceStatus::class),
                Filter::make('overdue')
                    ->label(__('billing.overdue_only'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Open)->where('due_at', '<', now())),
            ])
            ->emptyStateHeading(__('billing.invoices.empty'))
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
