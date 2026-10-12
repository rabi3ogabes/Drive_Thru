<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Tables;

use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Filament\Admin\Actions\LeadActions;
use App\Filament\Admin\Resources\Leads\LeadResource;
use App\Filament\Admin\Resources\Leads\Schemas\LeadForm;
use App\Support\Bidi;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['plan', 'assignee']))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('restaurant_name')
                    ->label(__('leads.fields.restaurant_name'))
                    ->description(fn (Lead $record): string => $record->contact_name)
                    ->weight('semibold')
                    ->searchable(['restaurant_name', 'contact_name']),
                TextColumn::make('email')
                    ->label(__('leads.fields.contact'))
                    ->description(fn (Lead $record): ?string => Bidi::ltr($record->phone))
                    ->copyable()
                    ->searchable(['email', 'phone']),
                TextColumn::make('branches_count')
                    ->label(__('leads.fields.branches_count'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('plan.name')->label(__('leads.fields.plan'))->placeholder('—')->toggleable(),
                TextColumn::make('status')->label(__('clients.fields.status'))->badge(),
                TextColumn::make('assignee.name')->label(__('leads.fields.assignee'))->placeholder(__('leads.unassigned'))->toggleable(),
                TextColumn::make('follow_up_at')
                    ->label(__('leads.fields.follow_up_at'))
                    ->since()
                    ->placeholder('—')
                    ->color(fn (Lead $record): ?string => $record->isFollowUpDue() ? 'danger' : null)
                    ->icon(fn (Lead $record): ?string => $record->isFollowUpDue() ? 'heroicon-m-bell-alert' : null)
                    ->sortable(),
                TextColumn::make('source')->label(__('leads.fields.source'))->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('leads.fields.received'))->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('assigned_to')->label(__('leads.fields.assignee'))->options(fn (): array => LeadForm::salesTeam()),
                SelectFilter::make('source')->label(__('leads.fields.source'))->options(LeadSource::class),
                Filter::make('mine')
                    ->label(__('leads.filters.mine'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('assigned_to', auth()->id())),
                Filter::make('follow_up_due')
                    ->label(__('leads.filters.follow_up_due'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', LeadStatus::open())->where('follow_up_at', '<=', now())),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    LeadActions::assignToMe(),
                    LeadActions::changeStatus(),
                    LeadActions::addNote(),
                    LeadActions::whatsapp(),
                    LeadActions::convert(),
                ]),
            ])
            ->emptyStateHeading(__('leads.empty.heading'))
            ->emptyStateDescription(__('leads.empty.description'))
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
