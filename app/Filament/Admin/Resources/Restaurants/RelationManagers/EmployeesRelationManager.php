<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\RelationManagers;

use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the client's team for the platform. The owner manages
 * the team from their workspace.
 */
class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('clients.team.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'position']))
            ->defaultSort('is_owner', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('clients.team.name'))
                    ->description(fn (Employee $record): string => $record->user->email ?? '')
                    ->weight('medium')
                    ->searchable(['name', 'email']),
                TextColumn::make('role')
                    ->label(__('clients.team.position'))
                    ->state(fn (Employee $record): string => $record->is_owner ? __('staff.owner') : ($record->position->name ?? '—'))
                    ->badge()
                    ->color(fn (Employee $record): string => $record->is_owner ? 'warning' : 'gray'),
                TextColumn::make('status')->label(__('clients.fields.status'))->badge(),
                TextColumn::make('user.last_login_at')
                    ->label(__('clients.team.last_seen'))
                    ->since()
                    ->placeholder(__('clients.team.never')),
                TextColumn::make('joined_at')->label(__('clients.team.joined'))->date('Y-m-d')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('clients.fields.status'))->options(EmployeeStatus::class),
            ])
            ->emptyStateHeading(__('clients.team.empty'))
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
