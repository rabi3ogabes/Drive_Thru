<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Tables;

use App\Domain\Billing\Models\Plan;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RestaurantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                // A layout component (Stack) switches the table into card-grid mode.
                Stack::make([
                    ViewColumn::make('card')
                        ->view('filament.admin.client-card')
                        ->searchable(['name->en', 'name->ar', 'slug', 'contact_email', 'contact_phone']),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('clients.fields.status'))
                    ->options(RestaurantStatus::class)
                    ->multiple(),
                SelectFilter::make('plan')
                    ->label(__('clients.card.plan'))
                    ->options(fn (): array => Plan::query()->orderBy('sort')->get()->mapWithKeys(fn (Plan $p): array => [$p->id => $p->name])->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('subscription', fn (Builder $q) => $q->where('plan_id', $data['value']))),
            ])
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->emptyStateHeading(__('clients.empty.heading'))
            ->emptyStateDescription(__('clients.empty.description'))
            ->emptyStateIcon('heroicon-o-building-storefront');
    }
}
