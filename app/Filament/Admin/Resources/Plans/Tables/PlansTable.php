<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Tables;

use App\Domain\Billing\Models\Plan;
use App\Support\Digits;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['subscriptions as live_subscriptions_count' => fn ($q) => $q->whereIn('status', ['trialing', 'active', 'past_due'])]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('plans.fields.name'))
                    ->weight('semibold')
                    ->description(fn (Plan $plan): ?string => $plan->description)
                    ->badge(fn (Plan $plan): bool => $plan->is_highlighted)
                    ->color(fn (Plan $plan): ?string => $plan->is_highlighted ? 'warning' : null)
                    ->searchable(['name->en', 'name->ar', 'key']),
                TextColumn::make('price_minor')
                    ->label(__('plans.fields.price'))
                    ->state(fn (Plan $plan): string => $plan->formattedPrice())
                    ->description(fn (Plan $plan): string => $plan->isCustomPriced() ? '' : __('plans.per_branch_per', ['interval' => $plan->interval->getLabel()])),
                TextColumn::make('trial_days')
                    ->label(__('plans.fields.trial'))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? trans_choice('plans.trial_days_count', $state, ['count' => $state]) : __('plans.no_trial')),
                TextColumn::make('limits')
                    ->label(__('plans.fields.limits'))
                    ->state(fn (Plan $plan): string => __('plans.limits_summary', [
                        'branches' => $plan->limit('branches') !== null ? Digits::number((int) $plan->limit('branches')) : '∞',
                        'employees' => $plan->limit('employees') !== null ? Digits::number((int) $plan->limit('employees')) : '∞',
                    ])),
                TextColumn::make('features')
                    ->label(__('plans.fields.features'))
                    ->state(fn (Plan $plan): int => $plan->features->count())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('live_subscriptions_count')
                    ->label(__('plans.fields.subscribers'))
                    ->numeric()
                    ->alignCenter(),
                IconColumn::make('is_public')->label(__('plans.fields.is_public'))->boolean()->alignCenter(),
                ToggleColumn::make('is_active')->label(__('plans.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading(__('plans.empty.heading'))
            ->emptyStateDescription(__('plans.empty.description'))
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }
}
