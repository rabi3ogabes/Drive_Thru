<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu;

use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\MenuSchedule;
use App\Filament\App\Resources\Menu\Pages\ManageMenuCategories;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;

    protected static ?string $slug = 'menu/sections';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Menu;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('menu.sections.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('menu.sections.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Bilingual::text('name', __('menu.sections.fields.name'), maxLength: 60),
            Bilingual::textarea('description', __('menu.sections.fields.description'), maxLength: 240),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('icon')
                    ->label(__('menu.sections.fields.icon'))
                    ->helperText(__('menu.sections.fields.icon_hint'))
                    ->maxLength(8),
                Select::make('menu_schedule_id')
                    ->label(__('menu.sections.fields.schedule'))
                    ->helperText(__('menu.sections.fields.schedule_hint'))
                    ->options(fn (): array => MenuSchedule::query()->get()->mapWithKeys(fn (MenuSchedule $s): array => [$s->id => $s->name.' · '.$s->starts_at.'–'.$s->ends_at])->all())
                    ->placeholder(__('menu.sections.fields.always'))
                    ->native(false),
            ]),
            FileUpload::make('image_path')
                ->label(__('menu.sections.fields.image'))
                ->image()
                ->imageEditor()
                ->disk('public')
                ->directory(fn (): string => 'menu/'.Workspace::restaurant()->id.'/sections')
                ->visibility('public')
                ->maxSize(3072),
            Toggle::make('is_visible')->label(__('menu.sections.fields.visible'))->helperText(__('menu.sections.fields.visible_hint'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('schedule')->withCount('products'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->columns([
                ImageColumn::make('image_path')->label('')->disk('public')->square()->imageSize(44)
                    ->defaultImageUrl(null)
                    ->visible(fn (): bool => MenuCategory::query()->whereNotNull('image_path')->exists()),
                TextColumn::make('name')
                    ->label(__('menu.sections.fields.name'))
                    ->formatStateUsing(fn (MenuCategory $record): string => trim(($record->icon ? $record->icon.'  ' : '').$record->name))
                    ->description(fn (MenuCategory $record): ?string => $record->description)
                    ->weight('semibold')
                    ->searchable(['name->en', 'name->ar']),
                TextColumn::make('products_count')->label(__('menu.sections.fields.items'))->numeric()->alignCenter(),
                TextColumn::make('schedule.name')
                    ->label(__('menu.sections.fields.schedule'))
                    ->badge()
                    ->color('info')
                    ->placeholder(__('menu.sections.fields.always')),
                ToggleColumn::make('is_visible')->label(__('menu.sections.fields.visible')),
            ])
            ->recordActions([
                EditAction::make()->mutateRecordDataUsing(fn (MenuCategory $record, array $data): array => array_merge($data, [
                    'name' => $record->getTranslations('name'),
                    'description' => $record->getTranslations('description'),
                ])),
                DeleteAction::make()->modalDescription(__('menu.sections.delete_description')),
            ])
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading(__('menu.sections.empty.heading'))
            ->emptyStateDescription(__('menu.sections.empty.description'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageMenuCategories::route('/')];
    }
}
