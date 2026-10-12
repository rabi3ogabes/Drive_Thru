<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Schemas;

use App\Domain\Branches\Enums\StationKind;
use App\Domain\Menu\Enums\Allergen;
use App\Domain\Menu\Enums\ProductTag;
use App\Domain\Menu\Enums\ProductType;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\MenuSchedule;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\Product;
use App\Filament\App\Resources\Menu\ModifierGroupResource;
use App\Filament\App\Workspace;
use App\Filament\Support\Bilingual;
use App\Filament\Support\MoneyInput;
use App\Support\Money;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * The menu item editor: basics, details (allergens, tags, flags), options &
 * suggestions, and, for meals, the combo slots.
 */
final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make(__('menu.products.tabs.basics'))->icon('heroicon-o-sparkles')->schema(self::basics()),
                Tab::make(__('menu.products.tabs.details'))->icon('heroicon-o-information-circle')->schema(self::details()),
                Tab::make(__('menu.products.tabs.options'))->icon('heroicon-o-adjustments-horizontal')->schema(self::options()),
                Tab::make(__('menu.products.tabs.combo'))
                    ->icon('heroicon-o-squares-plus')
                    ->visible(fn (Get $get): bool => self::isCombo($get('type')))
                    ->schema(self::combo()),
            ]),
        ]);
    }

    /**
     * @return array<Component>
     */
    private static function basics(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 3])->schema([
                Grid::make(1)->columnSpan(['default' => 1, 'md' => 2])->schema([
                    ToggleButtons::make('type')
                        ->label(__('menu.products.fields.type'))
                        ->options(ProductType::class)
                        ->default(ProductType::Item)
                        ->inline()
                        ->live()
                        ->disabledOn('edit')
                        ->required(),
                    Bilingual::text('name', __('menu.products.fields.name'), maxLength: 120),
                    Bilingual::textarea('description', __('menu.products.fields.description'), maxLength: 500),
                    Select::make('menu_category_id')
                        ->label(__('menu.products.fields.section'))
                        ->options(fn (): array => MenuCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => trim(($c->icon ?? '').' '.$c->name)])->all())
                        ->searchable()
                        ->required(),
                ]),
                FileUpload::make('image_path')
                    ->label(__('menu.products.fields.photo'))
                    ->helperText(__('menu.products.fields.photo_hint'))
                    ->image()
                    ->imageEditor()
                    ->imageCropAspectRatio('4:3')
                    ->disk('public')
                    ->directory(fn (): string => 'menu/'.Workspace::restaurant()->id.'/originals')
                    ->visibility('public')
                    ->maxSize(6144),
            ]),
            Section::make(__('menu.products.sections.price'))
                ->compact()
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    MoneyInput::make('price_minor')
                        ->label(__('menu.products.fields.price'))
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('discount_percent')
                        ->label(__('menu.products.fields.discount'))
                        ->numeric()->integer()->minValue(0)->maxValue(90)->default(0)
                        ->suffix('%')
                        ->live(onBlur: true),
                    TextInput::make('sku')
                        ->label(__('menu.products.fields.sku'))
                        ->helperText(__('menu.products.fields.sku_hint'))
                        ->maxLength(40)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('restaurant_id', Workspace::restaurant()->id))
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Text::make(fn (Get $get): ?string => self::pricePreview($get('price_minor'), $get('discount_percent')))
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    private static function details(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextInput::make('calories')->label(__('menu.products.fields.calories'))->numeric()->integer()->minValue(0)->maxValue(5000)->suffix('kcal'),
                TextInput::make('prep_minutes')->label(__('menu.products.fields.prep_minutes'))->helperText(__('menu.products.fields.prep_minutes_hint'))->numeric()->integer()->minValue(0)->maxValue(120)->suffix(__('branches.units.minutes')),
                Select::make('station')
                    ->label(__('menu.products.fields.station'))
                    ->helperText(__('menu.products.fields.station_hint'))
                    ->options(StationKind::class)
                    ->placeholder(StationKind::General->getLabel())
                    ->native(false),
                Select::make('menu_schedule_id')
                    ->label(__('menu.products.fields.schedule'))
                    ->helperText(__('menu.products.fields.schedule_hint'))
                    ->options(fn (): array => MenuSchedule::query()->get()->mapWithKeys(fn (MenuSchedule $s): array => [$s->id => $s->name])->all())
                    ->placeholder(__('menu.products.fields.follow_section'))
                    ->native(false),
            ]),
            Bilingual::textarea('ingredients', __('menu.products.fields.ingredients'), maxLength: 500),
            CheckboxList::make('allergens')
                ->label(__('menu.products.fields.allergens'))
                ->options(collect(Allergen::cases())->mapWithKeys(fn (Allergen $a): array => [$a->value => $a->symbol().'  '.$a->getLabel()])->all())
                ->columns(['default' => 2, 'md' => 4]),
            CheckboxList::make('tags')
                ->label(__('menu.products.fields.tags'))
                ->options(collect(ProductTag::cases())->mapWithKeys(fn (ProductTag $t): array => [$t->value => $t->symbol().'  '.$t->getLabel()])->all())
                ->columns(['default' => 2, 'md' => 4]),
            Section::make(__('menu.products.sections.highlights'))
                ->compact()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    Toggle::make('is_popular')->label(__('menu.products.fields.popular')),
                    Toggle::make('is_new')->label(__('menu.products.fields.new')),
                    Toggle::make('is_limited')->label(__('menu.products.fields.limited')),
                    Toggle::make('is_available')->label(__('menu.products.fields.available'))->default(true)
                        ->helperText(__('menu.products.fields.available_hint')),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    private static function options(): array
    {
        return [
            Select::make('modifierGroups')
                ->label(__('menu.products.fields.option_groups'))
                ->helperText(__('menu.products.fields.option_groups_hint'))
                ->relationship('modifierGroups', 'id', modifyQueryUsing: fn (Builder $query) => $query->orderBy('sort'))
                ->getOptionLabelFromRecordUsing(fn (ModifierGroup $g): string => $g->name.' · '.ModifierGroupResource::rule($g))
                ->multiple()
                ->preload()
                ->searchable(),
            Select::make('meal_product_id')
                ->label(__('menu.products.fields.meal'))
                ->helperText(__('menu.products.fields.meal_hint'))
                ->options(fn (?Product $record): array => Product::query()
                    ->where('type', ProductType::Combo)
                    ->when($record, fn (Builder $q, Product $product) => $q->whereKeyNot($product->id))
                    ->get()
                    ->mapWithKeys(fn (Product $p): array => [$p->id => $p->name])
                    ->all())
                ->placeholder(__('menu.products.fields.no_meal'))
                ->searchable()
                ->hidden(fn (Get $get): bool => self::isCombo($get('type'))),
            Select::make('recommendations')
                ->label(__('menu.products.fields.recommendations'))
                ->helperText(__('menu.products.fields.recommendations_hint'))
                ->relationship('recommendations', 'id', modifyQueryUsing: fn (Builder $query, ?Product $record) => $query->when($record, fn (Builder $q, Product $product) => $q->whereKeyNot($product->id)))
                ->getOptionLabelFromRecordUsing(fn (Product $p): string => $p->name)
                ->multiple()
                ->maxItems(3)
                ->preload()
                ->searchable(),
        ];
    }

    /**
     * @return array<Component>
     */
    private static function combo(): array
    {
        return [
            Repeater::make('comboSlots')
                ->relationship()
                ->label(__('menu.products.fields.slots'))
                ->helperText(__('menu.products.fields.slots_hint'))
                ->schema([
                    Bilingual::text('name', __('menu.products.fields.slot_name'), maxLength: 40),
                    Repeater::make('options')
                        ->relationship()
                        ->label(__('menu.products.fields.slot_options'))
                        ->table([
                            Repeater\TableColumn::make(__('menu.products.fields.slot_product'))->markAsRequired(),
                            Repeater\TableColumn::make(__('menu.products.fields.upcharge')),
                            Repeater\TableColumn::make(__('menu.products.fields.default'))->alignment('center'),
                        ])
                        ->schema([
                            Select::make('product_id')
                                ->options(fn (): array => Product::query()->where('type', ProductType::Item)->orderBy('sort')->get()->mapWithKeys(fn (Product $p): array => [$p->id => $p->name])->all())
                                ->searchable()
                                ->required(),
                            MoneyInput::make('price_delta_minor')->default(0),
                            Toggle::make('is_default'),
                        ])
                        ->orderColumn('sort')
                        ->minItems(1)
                        ->addActionLabel(__('menu.products.actions.add_slot_option'))
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['restaurant_id' => Workspace::restaurant()->id]),
                ])
                ->orderColumn('sort')
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'][app()->getLocale()] ?? $state['name']['en'] ?? null)
                ->addActionLabel(__('menu.products.actions.add_slot'))
                ->mutateRelationshipDataBeforeFillUsing(Bilingual::fillRelated(ComboSlot::class, ['name']))
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['restaurant_id' => Workspace::restaurant()->id]),
        ];
    }

    /** "Guests pay 22.50 QAR (save 2.50)" under the price fields. */
    private static function pricePreview(mixed $price, mixed $discount): ?string
    {
        if (! is_numeric($price)) {
            return null;
        }

        $currency = Workspace::restaurant()->currency;
        $minor = Money::toMinor((string) $price, $currency);
        $final = (int) round($minor * (100 - (int) $discount) / 100);

        return $final < $minor
            ? __('menu.products.pays_discounted', ['price' => Money::format($final, $currency), 'save' => Money::format($minor - $final, $currency)])
            : __('menu.products.pays', ['price' => Money::format($final, $currency)]);
    }

    private static function isCombo(mixed $type): bool
    {
        return $type === ProductType::Combo || $type === ProductType::Combo->value;
    }
}
