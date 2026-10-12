<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu;

use App\Domain\Menu\Enums\ProductType;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Filament\App\Actions\MenuActions;
use App\Filament\App\Resources\Menu\Pages\CreateProduct;
use App\Filament\App\Resources\Menu\Pages\EditProduct;
use App\Filament\App\Resources\Menu\Pages\ListProducts;
use App\Filament\App\Resources\Menu\Schemas\ProductForm;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'menu/items';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Menu;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('menu.products.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('menu.products.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $currency = fn (): string => Workspace::restaurant()->currency;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->groups([
                Group::make('category.sort')
                    ->label(__('menu.products.fields.section'))
                    ->getTitleFromRecordUsing(fn (Product $record): string => trim(($record->category->icon ?? '').' '.($record->category->name ?? __('menu.products.no_section'))))
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('category.sort')
            ->groupingSettingsHidden()
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->state(fn (Product $record): ?string => $record->imageUrl(400))
                    ->imageSize(48)
                    ->extraImgAttributes(['class' => 'rounded-xl object-cover'])
                    ->defaultImageUrl(null)
                    ->visible(fn (): bool => Product::query()->whereNotNull('image_path')->exists()),
                TextColumn::make('name')
                    ->label(__('menu.products.fields.name'))
                    ->description(fn (Product $record): ?string => $record->isCombo() ? $record->type->getLabel() : null)
                    ->weight('semibold')
                    ->searchable(['name->en', 'name->ar', 'sku'])
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-56']),
                TextColumn::make('price_minor')
                    ->label(__('menu.products.fields.price'))
                    ->html()
                    ->formatStateUsing(fn (Product $record): HtmlString => new HtmlString(
                        ($record->discount_percent > 0
                            ? '<span class="me-1.5 text-xs text-gray-400 line-through">'.e(Money::format($record->price_minor, $currency())).'</span>'
                            : '')
                        .'<span class="font-semibold tabular-nums">'.e(Money::format($record->finalPriceMinor(), $currency())).'</span>'
                    ))
                    ->sortable(),
                TextColumn::make('highlights')
                    ->label(__('menu.products.fields.highlights'))
                    ->state(fn (Product $record): array => array_values(array_filter([
                        $record->is_popular ? __('menu.products.fields.popular') : null,
                        $record->is_new ? __('menu.products.fields.new') : null,
                        $record->is_limited ? __('menu.products.fields.limited') : null,
                        $record->discount_percent > 0 ? '−'.$record->discount_percent.'%' : null,
                    ])))
                    ->badge()
                    ->color('warning'),
                ToggleColumn::make('is_available')
                    ->label(__('menu.products.fields.on_menu'))
                    ->tooltip(__('menu.products.fields.available_hint')),
            ])
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label(__('menu.products.fields.section'))
                    ->options(fn (): array => MenuCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->name])->all()),
                SelectFilter::make('type')->label(__('menu.products.fields.type'))->options(ProductType::class),
                TernaryFilter::make('is_available')->label(__('menu.products.fields.on_menu')),
                TernaryFilter::make('discounted')
                    ->label(__('menu.products.fields.discounted'))
                    ->queries(
                        true: fn (Builder $q) => $q->where('discount_percent', '>', 0),
                        false: fn (Builder $q) => $q->where('discount_percent', 0),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    MenuActions::duplicate(),
                    DeleteAction::make()->modalDescription(__('menu.products.delete_description')),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    MenuActions::bulkPrice(),
                    MenuActions::moveToSection(),
                    MenuActions::setAvailable(true),
                    MenuActions::setAvailable(false),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateIcon('heroicon-o-squares-2x2')
            ->emptyStateHeading(__('menu.products.empty.heading'))
            ->emptyStateDescription(__('menu.products.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
