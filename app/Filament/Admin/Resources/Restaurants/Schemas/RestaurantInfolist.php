<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Schemas;

use App\Domain\Billing\Actions\SuspendRestaurant;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Support\Bidi;
use App\Support\Digits;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RestaurantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('clients.sections.subscription'))
                ->icon('heroicon-o-credit-card')
                ->columnSpan(['default' => 3, 'lg' => 2])
                ->columns(['default' => 2, 'md' => 3, '2xl' => 4])
                ->schema([
                    TextEntry::make('status')->label(__('clients.fields.status'))->badge(),
                    TextEntry::make('subscription.plan.name')->label(__('clients.fields.plan'))->placeholder('—')->weight('semibold'),
                    TextEntry::make('subscription.status')
                        ->label(__('clients.fields.billing_status'))
                        ->badge()
                        ->placeholder('—')
                        ->helperText(fn (Restaurant $record): ?string => ($left = $record->subscription?->trialDaysLeft()) !== null
                            ? trans_choice('clients.card.trial_left', $left, ['count' => $left])
                            : null),
                    TextEntry::make('subscription.quantity')
                        ->label(__('clients.fields.branches'))
                        ->formatStateUsing(fn (?int $state): string => Digits::number((int) $state))
                        ->placeholder('—'),
                    TextEntry::make('period_amount')
                        ->label(__('clients.fields.period_amount'))
                        ->state(fn (Restaurant $record): string => $record->subscription
                            ? Money::format($record->subscription->amountPerPeriodMinor(), $record->subscription->currency)
                            : '—')
                        ->helperText(fn (Restaurant $record): ?string => $record->subscription !== null
                            ? trans_choice('clients.per_interval', 1, ['interval' => $record->subscription->interval->getLabel()])
                            : null)
                        ->extraAttributes(['class' => 'whitespace-nowrap']),
                    TextEntry::make('subscription.current_period_end')
                        ->label(fn (Restaurant $record): string => $record->subscription?->status === SubscriptionStatus::Trialing ? __('clients.fields.trial_ends') : __('clients.fields.next_invoice'))
                        ->date('Y-m-d')
                        ->placeholder('—'),
                    TextEntry::make('subscription.grace_ends_at')
                        ->label(__('clients.fields.grace_ends'))
                        ->date('Y-m-d')
                        ->color('warning')
                        ->visible(fn (Restaurant $record): bool => $record->subscription?->status === SubscriptionStatus::PastDue),
                    TextEntry::make('suspension_reason')
                        ->label(__('clients.fields.suspension_reason'))
                        ->formatStateUsing(fn (?string $state): string => $state === SuspendRestaurant::BILLING_REASON ? __('clients.suspension.billing') : (string) $state)
                        ->color('danger')
                        ->columnSpan(2)
                        ->visible(fn (Restaurant $record): bool => filled($record->suspension_reason)),
                ]),

            Section::make(__('clients.sections.owner'))
                ->icon('heroicon-o-user-circle')
                ->columnSpan(['default' => 3, 'lg' => 1])
                ->schema([
                    TextEntry::make('owner_name')
                        ->label(__('clients.fields.owner_name'))
                        ->state(fn (Restaurant $record): ?string => $record->owner()?->name)
                        ->placeholder(__('clients.no_owner')),
                    TextEntry::make('owner_email')
                        ->label(__('clients.fields.owner_email'))
                        ->state(fn (Restaurant $record): ?string => $record->owner()?->email)
                        ->copyable()
                        ->placeholder('—'),
                    TextEntry::make('owner_state')
                        ->label(__('clients.fields.owner_state'))
                        ->state(fn (Restaurant $record): string => $record->owner()?->last_login_at !== null
                            ? __('clients.owner_state.active', ['when' => $record->owner()->last_login_at->diffForHumans()])
                            : __('clients.owner_state.pending'))
                        ->badge()
                        ->color(fn (Restaurant $record): string => $record->owner()?->last_login_at !== null ? 'success' : 'warning'),
                ]),

            Section::make(__('clients.sections.billing_details'))
                ->icon('heroicon-o-building-office-2')
                ->columnSpan(['default' => 3, 'lg' => 2])
                ->columns(['default' => 1, 'md' => 2])
                ->collapsible()
                ->schema([
                    TextEntry::make('legal_name')->label(__('clients.fields.legal_name'))->placeholder('—'),
                    TextEntry::make('tax_number')->label(__('clients.fields.tax_number'))->placeholder('—'),
                    TextEntry::make('contact_email')->label(__('clients.fields.contact_email'))->copyable()->placeholder('—'),
                    TextEntry::make('contact_phone')->label(__('clients.fields.contact_phone'))->formatStateUsing(fn (?string $state): ?string => Bidi::ltr($state))->copyable()->copyableState(fn (Restaurant $record): ?string => $record->contact_phone)->placeholder('—'),
                    TextEntry::make('billing_address')->label(__('clients.fields.billing_address'))->placeholder('—')->columnSpanFull(),
                    TextEntry::make('slug')
                        ->label(__('clients.fields.workspace_link'))
                        ->formatStateUsing(fn (string $state): string => url('/app/'.$state))
                        ->url(fn (string $state): string => url('/app/'.$state), shouldOpenInNewTab: true)
                        ->copyable()
                        ->copyableState(fn (string $state): string => url('/app/'.$state)),
                    TextEntry::make('created_at')->label(__('clients.fields.client_since'))->date('Y-m-d'),
                ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('clients.sections.features'))
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        TextEntry::make('effective_features')
                            ->hiddenLabel()
                            ->state(fn (Restaurant $record): array => collect(PlanFeature::cases())
                                ->filter(fn (PlanFeature $f): bool => $record->hasFeature($f))
                                ->map(fn (PlanFeature $f): string => $f->getLabel())
                                ->values()
                                ->all())
                            ->badge()
                            ->color('primary')
                            ->placeholder(__('clients.no_features')),
                    ]),
                Section::make(__('clients.sections.internal'))
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (Restaurant $record): bool => filled($record->internal_notes))
                    ->schema([
                        TextEntry::make('internal_notes')->hiddenLabel()->prose(),
                    ]),
            ]),
        ]);
    }
}
