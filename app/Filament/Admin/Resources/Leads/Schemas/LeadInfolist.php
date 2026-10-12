<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Schemas;

use App\Domain\Growth\Models\Lead;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use App\Support\Bidi;
use App\Support\Locales;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('leads.sections.request'))
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('contact_name')->label(__('leads.fields.contact_name'))->weight('semibold'),
                        TextEntry::make('email')->label(__('leads.fields.email'))->copyable()->url(fn (Lead $record): string => 'mailto:'.$record->email),
                        TextEntry::make('phone')->label(__('leads.fields.phone'))->formatStateUsing(fn (?string $state): ?string => Bidi::ltr($state))->copyable()->copyableState(fn (Lead $record): ?string => $record->phone)->placeholder('—'),
                        TextEntry::make('branches_count')->label(__('leads.fields.branches_count')),
                        TextEntry::make('plan.name')->label(__('leads.fields.plan'))->placeholder('—'),
                        TextEntry::make('locale')->label(__('leads.fields.locale'))->formatStateUsing(fn (string $state): string => Locales::native($state)),
                        TextEntry::make('message')->label(__('leads.fields.message'))->placeholder('—')->columnSpanFull()->prose(),
                    ]),
                Section::make(__('leads.sections.notes'))
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->schema([
                        RepeatableEntry::make('notes')
                            ->hiddenLabel()
                            ->contained(false)
                            ->placeholder(__('leads.no_notes'))
                            ->schema([
                                TextEntry::make('body')
                                    ->hiddenLabel()
                                    ->helperText(fn ($record): string => trim(($record->author->name ?? __('audit.system')).' · '.$record->created_at?->diffForHumans()))
                                    ->prose(),
                            ]),
                    ]),
            ]),
            Section::make(__('leads.sections.pipeline'))
                ->icon('heroicon-o-funnel')
                ->columnSpan(['default' => 3, 'lg' => 1])
                ->schema([
                    TextEntry::make('status')->label(__('clients.fields.status'))->badge(),
                    TextEntry::make('lost_reason')->label(__('leads.fields.lost_reason'))->color('danger')->visible(fn (Lead $record): bool => filled($record->lost_reason)),
                    TextEntry::make('convertedRestaurant.name')
                        ->label(__('leads.fields.converted_to'))
                        ->url(fn (Lead $record): ?string => $record->convertedRestaurant ? RestaurantResource::getUrl('view', ['record' => $record->convertedRestaurant]) : null)
                        ->visible(fn (Lead $record): bool => $record->converted_restaurant_id !== null),
                    TextEntry::make('assignee.name')->label(__('leads.fields.assignee'))->placeholder(__('leads.unassigned')),
                    TextEntry::make('follow_up_at')
                        ->label(__('leads.fields.follow_up_at'))
                        ->dateTime('Y-m-d H:i')
                        ->color(fn (Lead $record): ?string => $record->isFollowUpDue() ? 'danger' : null)
                        ->placeholder('—'),
                    TextEntry::make('source')->label(__('leads.fields.source'))->badge()->color('gray'),
                    TextEntry::make('created_at')->label(__('leads.fields.received'))->dateTime('Y-m-d H:i'),
                    TextEntry::make('contacted_at')->label(__('leads.fields.contacted_at'))->dateTime('Y-m-d H:i')->placeholder('—'),
                ]),
        ]);
    }
}
