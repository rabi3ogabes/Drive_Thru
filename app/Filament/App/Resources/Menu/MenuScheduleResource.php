<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu;

use App\Domain\Menu\Models\MenuSchedule;
use App\Filament\App\Resources\Branches\Schemas\BranchForm;
use App\Filament\App\Resources\Menu\Pages\ManageMenuSchedules;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class MenuScheduleResource extends Resource
{
    protected static ?string $model = MenuSchedule::class;

    protected static ?string $slug = 'menu/schedules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Menu;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('menu.schedules.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('menu.schedules.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Bilingual::text('name', __('menu.schedules.fields.name'), maxLength: 60),
            CheckboxList::make('weekdays')
                ->label(__('menu.schedules.fields.days'))
                ->options(BranchForm::weekdays())
                ->default(range(0, 6))
                ->columns(['default' => 2, 'sm' => 4])
                ->bulkToggleable()
                ->required(),
            Grid::make(2)->schema([
                TimePicker::make('starts_at')->label(__('menu.schedules.fields.starts_at'))->seconds(false)->timezone('UTC')->required(),
                TimePicker::make('ends_at')->label(__('menu.schedules.fields.ends_at'))->seconds(false)->timezone('UTC')->required()
                    ->helperText(__('menu.schedules.fields.ends_at_hint')),
            ]),
            Toggle::make('is_active')->label(__('menu.schedules.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('menu.schedules.fields.name'))->weight('semibold')->searchable(['name->en', 'name->ar']),
                TextColumn::make('window')
                    ->label(__('menu.schedules.fields.window'))
                    ->state(fn (MenuSchedule $record): string => $record->starts_at.' – '.$record->ends_at)
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('weekdays')
                    ->label(__('menu.schedules.fields.days'))
                    ->state(fn (MenuSchedule $record): string => count($record->weekdays) === 7
                        ? __('menu.schedules.every_day')
                        : collect($record->weekdays)->sort()->map(fn (int $d): string => BranchForm::weekdays()[$d])->join('، ')),
                ToggleColumn::make('is_active')->label(__('menu.schedules.fields.active')),
            ])
            ->recordActions([
                EditAction::make()->mutateRecordDataUsing(fn (MenuSchedule $record, array $data): array => array_merge($data, ['name' => $record->getTranslations('name')])),
                DeleteAction::make()->modalDescription(__('menu.schedules.delete_description')),
            ])
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading(__('menu.schedules.empty.heading'))
            ->emptyStateDescription(__('menu.schedules.empty.description'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageMenuSchedules::route('/')];
    }
}
