<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu;

use App\Domain\Menu\Enums\ModifierKind;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\ModifierOption;
use App\Filament\App\Resources\Menu\Pages\ManageModifierGroups;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use App\Filament\Support\MoneyInput;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ModifierGroupResource extends Resource
{
    protected static ?string $model = ModifierGroup::class;

    protected static ?string $slug = 'menu/options';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Menu;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('menu.options.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('menu.options.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Bilingual::text('name', __('menu.options.fields.name'), maxLength: 60),
            ToggleButtons::make('kind')
                ->label(__('menu.options.fields.kind'))
                ->options(ModifierKind::class)
                ->default(ModifierKind::Choice)
                ->inline()
                ->live()
                ->required(),
            Grid::make(['default' => 2])->schema([
                TextInput::make('min_select')
                    ->label(__('menu.options.fields.min'))
                    ->helperText(__('menu.options.fields.min_hint'))
                    ->numeric()->integer()->minValue(0)->maxValue(20)->default(0)->required(),
                TextInput::make('max_select')
                    ->label(__('menu.options.fields.max'))
                    ->helperText(__('menu.options.fields.max_hint'))
                    ->numeric()->integer()->minValue(1)->maxValue(20)
                    ->gte('min_select'),
            ]),
            Repeater::make('options')
                ->relationship()
                ->label(__('menu.options.fields.options'))
                ->schema([
                    Bilingual::text('name', __('menu.options.fields.option_name'), maxLength: 60),
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        MoneyInput::make('price_delta_minor', allowNegative: true)
                            ->label(__('menu.options.fields.price_delta'))
                            ->default(0)
                            ->hidden(fn (Get $get): bool => self::isRemoveGroup($get('../../kind'))),
                        TextInput::make('calories_delta')->label(__('menu.options.fields.calories'))->numeric()->integer()->minValue(-2000)->maxValue(2000),
                        Toggle::make('is_default')->label(__('menu.options.fields.default'))->inline(false),
                        Toggle::make('is_available')->label(__('menu.options.fields.available'))->default(true)->inline(false),
                    ]),
                    Select::make('nested_group_id')
                        ->label(__('menu.options.fields.nested'))
                        ->helperText(__('menu.options.fields.nested_hint'))
                        ->options(fn (?ModifierOption $record): array => ModifierGroup::query()
                            ->when($record, fn (Builder $q, ModifierOption $option) => $q->whereKeyNot($option->modifier_group_id))
                            ->get()
                            ->mapWithKeys(fn (ModifierGroup $g): array => [$g->id => $g->name])
                            ->all())
                        ->placeholder(__('menu.options.fields.no_nested'))
                        ->native(false)
                        ->hidden(fn (Get $get): bool => self::isRemoveGroup($get('../../kind'))),
                ])
                ->orderColumn('sort')
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'][app()->getLocale()] ?? $state['name']['en'] ?? null)
                ->addActionLabel(__('menu.options.actions.add_option'))
                ->minItems(1)
                ->mutateRelationshipDataBeforeFillUsing(Bilingual::fillRelated(ModifierOption::class, ['name']))
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['restaurant_id' => Workspace::restaurant()->id]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('options')->withCount('products'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->label(__('menu.options.fields.name'))
                    ->description(fn (ModifierGroup $record): string => $record->options
                        ->map(fn (ModifierOption $o): string => $o->name.($o->price_delta_minor !== 0 ? ' (+'.Money::format($o->price_delta_minor, Workspace::restaurant()->currency).')' : ''))
                        ->take(5)->join(' · ').($record->options->count() > 5 ? ' …' : ''))
                    ->weight('semibold')
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-72 max-w-xl'])
                    ->searchable(['name->en', 'name->ar']),
                TextColumn::make('kind')->label(__('menu.options.fields.kind'))->badge()->color('gray'),
                TextColumn::make('rule')
                    ->label(__('menu.options.fields.rule'))
                    ->state(fn (ModifierGroup $record): string => self::rule($record))
                    ->badge()
                    ->color(fn (ModifierGroup $record): string => $record->isRequired() ? 'warning' : 'gray'),
                TextColumn::make('products_count')->label(__('menu.options.fields.used_by'))->numeric()->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->mutateRecordDataUsing(fn (ModifierGroup $record, array $data): array => array_merge($data, ['name' => $record->getTranslations('name')])),
                DeleteAction::make()->modalDescription(__('menu.options.delete_description')),
            ])
            ->emptyStateIcon('heroicon-o-adjustments-horizontal')
            ->emptyStateHeading(__('menu.options.empty.heading'))
            ->emptyStateDescription(__('menu.options.empty.description'));
    }

    /** "Required · pick 1", "Optional · up to 3", "Optional". */
    public static function rule(ModifierGroup $group): string
    {
        $pick = match (true) {
            $group->max_select === 1 => __('menu.options.rules.one'),
            $group->max_select !== null && $group->min_select === $group->max_select => __('menu.options.rules.exactly', ['count' => $group->max_select]),
            $group->max_select !== null => __('menu.options.rules.up_to', ['count' => $group->max_select]),
            $group->min_select > 0 => __('menu.options.rules.at_least', ['count' => $group->min_select]),
            default => __('menu.options.rules.any'),
        };

        return ($group->isRequired() ? __('menu.options.rules.required') : __('menu.options.rules.optional')).' · '.$pick;
    }

    private static function isRemoveGroup(mixed $kind): bool
    {
        return $kind === ModifierKind::Remove || $kind === ModifierKind::Remove->value;
    }

    public static function getPages(): array
    {
        return ['index' => ManageModifierGroups::route('/')];
    }
}
