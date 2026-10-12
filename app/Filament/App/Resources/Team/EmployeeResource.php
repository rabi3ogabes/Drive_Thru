<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Team;

use App\Domain\Access\Models\Position;
use App\Domain\Branches\Models\Branch;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Filament\App\Actions\TeamActions;
use App\Filament\App\Resources\Team\Pages\ManageTeam;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'team';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Team;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('staff.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('staff.nav');
    }

    /**
     * Branch-level managers see the people in their own branches (and themselves).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $ids = Workspace::branchIds();

        if ($ids === null) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('user_id', Workspace::user()->id)
            ->orWhere(fn (Builder $q) => $q
                ->where('is_owner', false)
                ->where('all_branches', false)
                ->whereHas('branches', fn (Builder $b) => $b->whereIn('branches.id', $ids))));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'position', 'branches', 'restaurant']))
            ->defaultSort('is_owner', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('staff.fields.name'))
                    ->description(fn (Employee $record): string => $record->user->email)
                    ->weight('semibold')
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('staff.fields.position'))
                    ->state(fn (Employee $record): string => $record->is_owner ? __('staff.owner') : ($record->position->name ?? '—'))
                    ->badge()
                    ->color(fn (Employee $record): string => $record->is_owner ? 'warning' : 'gray'),
                TextColumn::make('coverage')
                    ->label(__('staff.fields.branches'))
                    ->state(fn (Employee $record): array => $record->coversAllBranches()
                        ? [__('staff.all_branches')]
                        : $record->branches->map(fn (Branch $branch): string => $branch->name)->all())
                    ->badge()
                    ->color('info')
                    ->limitList(2)
                    ->expandableLimitedList(),
                TextColumn::make('status')->label(__('staff.fields.status'))->badge(),
                IconColumn::make('pin')
                    ->label(__('staff.fields.pin'))
                    ->state(fn (Employee $record): bool => $record->hasPin())
                    ->boolean()
                    ->trueIcon('heroicon-o-key')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (Employee $record): string => $record->hasPin() ? __('staff.pin.is_set') : __('staff.pin.not_set')),
                TextColumn::make('user.last_login_at')
                    ->label(__('staff.fields.last_seen'))
                    ->since()
                    ->placeholder(__('staff.never'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('staff.fields.status'))->options(EmployeeStatus::class),
                SelectFilter::make('position_id')
                    ->label(__('staff.fields.position'))
                    ->options(fn (): array => Position::query()->orderBy('sort')->get()->mapWithKeys(fn (Position $p): array => [$p->id => $p->name])->all()),
                SelectFilter::make('branch')
                    ->label(__('staff.fields.branch'))
                    ->options(fn (): array => Workspace::branchOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where(fn (Builder $q) => $q->where('all_branches', true)->orWhere('is_owner', true)
                            ->orWhereHas('branches', fn (Builder $b) => $b->where('branches.id', $data['value'])))
                        : $query),
            ])
            ->recordActions([
                TeamActions::edit(),
                ActionGroup::make([
                    TeamActions::sendLink(),
                    TeamActions::setPin(),
                    TeamActions::suspend(),
                    TeamActions::reactivate(),
                    TeamActions::remove(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading(__('staff.empty.heading'))
            ->emptyStateDescription(__('staff.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeam::route('/'),
        ];
    }
}
