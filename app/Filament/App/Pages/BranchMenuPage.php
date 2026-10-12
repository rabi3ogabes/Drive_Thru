<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Menu\BranchMenu;
use App\Domain\Menu\Data\ProductOffer;
use App\Domain\Menu\Enums\Unavailability;
use App\Domain\Menu\Models\BranchProduct;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Filament\App\Actions\MenuActions;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\MoneyInput;
use App\Support\Lang;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Branch-first menu: choose a branch, then see every item as that branch
 * serves it right now and change it in one tap: serve or not, out of stock
 * today, daily stock, branch price. Built for the shift manager on a tablet.
 *
 * @property-read ?Branch $branch
 * @property-read SupportCollection<int, BranchProduct> $settings
 */
class BranchMenuPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'menu/branches';

    protected string $view = 'filament.app.pages.branch-menu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Menu;

    protected static ?int $navigationSort = 4;

    #[Url(as: 'branch')]
    public ?int $branchId = null;

    public static function canAccess(): bool
    {
        return Workspace::can(RestaurantPermission::ManageMenu) || Workspace::can(RestaurantPermission::ManageAvailability);
    }

    public static function getNavigationLabel(): string
    {
        return __('menu.branch_menu.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('menu.branch_menu.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('menu.branch_menu.subheading');
    }

    public function mount(): void
    {
        $options = Workspace::branchOptions();

        if ($this->branchId === null || ! array_key_exists($this->branchId, $options)) {
            $this->branchId = array_key_first($options);
        }
    }

    public function selectBranch(int $id): void
    {
        abort_unless(array_key_exists($id, Workspace::branchOptions()), 403);

        $this->branchId = $id;
        unset($this->branch, $this->settings);
        $this->resetTable();
    }

    #[Computed]
    public function branch(): ?Branch
    {
        return $this->branchId === null ? null : Workspace::scopeToBranches(Branch::query())->with('restaurant')->find($this->branchId);
    }

    /**
     * @return SupportCollection<int, BranchProduct>
     */
    #[Computed]
    public function settings(): SupportCollection
    {
        return $this->branch === null ? collect() : app(BranchMenu::class)->settingsFor($this->branch);
    }

    /**
     * Branch tiles with a live count of what's off right now.
     *
     * @return list<array{id: int, name: string, off: int}>
     */
    public function branchTiles(): array
    {
        $menu = app(BranchMenu::class);
        $products = Product::query()->with(['category.schedule', 'schedule'])->get();

        return array_values(Workspace::scopeToBranches(Branch::query())->with('restaurant')->orderBy('sort')->get()
            ->map(function (Branch $branch) use ($menu, $products): array {
                $settings = $menu->settingsFor($branch);
                $off = $products->filter(fn (Product $p): bool => in_array(
                    $menu->offer($p, $branch, $settings->get($p->id))->unavailable,
                    [Unavailability::OutOfStockToday, Unavailability::SoldOut, Unavailability::NotServedHere],
                    true,
                ))->count();

                return ['id' => $branch->id, 'name' => $branch->name, 'off' => $off];
            })
            ->all());
    }

    public function offerFor(Product $product): ?ProductOffer
    {
        return $this->branch === null ? null : app(BranchMenu::class)->offer($product, $this->branch, $this->settings->get($product->id));
    }

    public function table(Table $table): Table
    {
        $currency = fn (): string => Workspace::restaurant()->currency;
        $canPrice = Workspace::can(RestaurantPermission::ManageMenu);

        return $table
            ->query(fn (): Builder => Product::query()->with(['category.schedule', 'schedule']))
            ->defaultSort('sort')
            ->groups([
                Group::make('category.sort')
                    ->getTitleFromRecordUsing(fn (Product $record): string => trim(($record->category->icon ?? '').' '.($record->category->name ?? '—')))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
            ->defaultGroup('category.sort')
            ->groupingSettingsHidden()
            ->columns([
                ImageColumn::make('photo')->label('')->state(fn (Product $record): ?string => $record->imageUrl(400))->imageSize(44)
                    ->extraImgAttributes(['class' => 'rounded-xl object-cover'])
                    ->visible(fn (): bool => Product::query()->whereNotNull('image_path')->exists()),
                TextColumn::make('name')->label(__('menu.products.fields.name'))->weight('semibold')->searchable(['name->en', 'name->ar'])->wrap()
                    ->extraAttributes(['class' => 'min-w-48']),
                TextColumn::make('branch_price')
                    ->label(__('menu.branch_menu.price'))
                    ->state(fn (Product $record): string => Money::format($this->offerFor($record)->priceMinor ?? $record->price_minor, $currency()))
                    ->description(fn (Product $record): ?string => $this->settings->get($record->id)?->price_override_minor !== null ? Lang::text('menu.branch_menu.branch_price') : null)
                    ->extraAttributes(['class' => 'tabular-nums']),
                TextColumn::make('status')
                    ->label(__('menu.branch_menu.status'))
                    ->state(fn (Product $record): string => $this->offerFor($record)?->unavailable?->getLabel() ?? __('menu.branch_menu.on_sale'))
                    ->badge()
                    ->color(fn (Product $record): string => $this->offerFor($record)?->unavailable?->getColor() ?? 'success'),
                TextColumn::make('stock')
                    ->label(__('menu.branch_menu.stock'))
                    ->state(function (Product $record): string {
                        $offer = $this->offerFor($record);

                        return $offer?->stockLeft === null ? '∞' : __('menu.branch_menu.left', ['count' => $offer->stockLeft]);
                    })
                    ->color(fn (Product $record): string => $this->offerFor($record)?->lowStock === true ? 'danger' : 'gray')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label(__('menu.products.fields.section'))
                    ->options(fn (): array => MenuCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->name])->all()),
            ])
            ->recordActions([
                Action::make('outOfStock')
                    ->label(__('menu.branch_menu.actions.out_of_stock'))
                    ->icon('heroicon-m-archive-box-x-mark')
                    ->color('danger')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Product $record): bool => $this->offerFor($record)?->unavailable !== Unavailability::OutOfStockToday)
                    ->action(fn (Product $record) => $this->apply(fn (Branch $b) => app(BranchStock::class)->markOutOfStockToday($record, $b, Workspace::user()), __('menu.branch_menu.notifications.out_of_stock', ['product' => $record->name]))),
                Action::make('backInStock')
                    ->label(__('menu.branch_menu.actions.back_in_stock'))
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Product $record): bool => $this->offerFor($record)?->unavailable === Unavailability::OutOfStockToday)
                    ->action(fn (Product $record) => $this->apply(fn (Branch $b) => app(BranchStock::class)->backInStock($record, $b, Workspace::user()), __('menu.branch_menu.notifications.back', ['product' => $record->name]))),
                ActionGroup::make([
                    Action::make('dailyStock')
                        ->label(__('menu.branch_menu.actions.daily_stock'))
                        ->icon('heroicon-o-calculator')
                        ->modalDescription(__('menu.branch_menu.daily_stock_description'))
                        ->fillForm(fn (Product $record): array => [
                            'daily' => $this->settings->get($record->id)?->daily_stock,
                            'threshold' => $this->settings->get($record->id)?->low_stock_threshold,
                        ])
                        ->schema([
                            TextInput::make('daily')->label(__('menu.branch_menu.fields.daily'))->helperText(__('menu.branch_menu.fields.daily_hint'))->numeric()->integer()->minValue(0)->maxValue(100000),
                            TextInput::make('threshold')->label(__('menu.branch_menu.fields.threshold'))->helperText(__('menu.branch_menu.fields.threshold_hint'))->numeric()->integer()->minValue(0)->lte('daily'),
                        ])
                        ->action(fn (Product $record, array $data) => $this->apply(
                            fn (Branch $b) => app(BranchStock::class)->setDailyStock($record, $b, filled($data['daily']) ? (int) $data['daily'] : null, filled($data['threshold']) ? (int) $data['threshold'] : null, Workspace::user()),
                            __('menu.branch_menu.notifications.saved'),
                        )),
                    Action::make('branchPrice')
                        ->label(__('menu.branch_menu.actions.price'))
                        ->icon('heroicon-o-banknotes')
                        ->visible($canPrice)
                        ->modalDescription(fn (Product $record): string => __('menu.branch_menu.price_description', ['price' => Money::format($record->price_minor, $currency())]))
                        ->fillForm(fn (Product $record): array => ['price' => $this->settings->get($record->id)?->price_override_minor])
                        ->schema([MoneyInput::make('price')->label(__('menu.branch_menu.fields.price'))->helperText(__('menu.branch_menu.fields.price_hint'))])
                        ->action(fn (Product $record, array $data) => $this->apply(
                            fn (Branch $b) => app(BranchStock::class)->setPrice($record, $b, $data['price'] ?? null, Workspace::user()),
                            __('menu.branch_menu.notifications.saved'),
                        )),
                    Action::make('served')
                        ->label(fn (Product $record): string => $this->settings->get($record->id)?->is_available === false ? __('menu.branch_menu.actions.serve') : __('menu.branch_menu.actions.dont_serve'))
                        ->icon(fn (Product $record): string => $this->settings->get($record->id)?->is_available === false ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                        ->requiresConfirmation()
                        ->action(fn (Product $record) => $this->apply(
                            fn (Branch $b) => app(BranchStock::class)->setServed($record, $b, $this->settings->get($record->id)?->is_available === false, Workspace::user()),
                            __('menu.branch_menu.notifications.saved'),
                        )),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('bulkOutOfStock')
                    ->label(__('menu.branch_menu.actions.out_of_stock'))
                    ->icon('heroicon-m-archive-box-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->apply(
                        fn (Branch $b) => array_map(fn (Product $p) => app(BranchStock::class)->markOutOfStockToday($p, $b, Workspace::user()), MenuActions::products($records)),
                        __('menu.branch_menu.notifications.saved'),
                    ))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('bulkBack')
                    ->label(__('menu.branch_menu.actions.back_in_stock'))
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('success')
                    ->action(fn (Collection $records) => $this->apply(
                        fn (Branch $b) => array_map(fn (Product $p) => app(BranchStock::class)->backInStock($p, $b, Workspace::user()), MenuActions::products($records)),
                        __('menu.branch_menu.notifications.saved'),
                    ))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('menu.products.empty.heading'))
            ->emptyStateDescription(__('menu.products.empty.description'));
    }

    /**
     * Runs a change for the selected branch, then refreshes what the page shows.
     *
     * @param  callable(Branch): mixed  $change
     */
    private function apply(callable $change, string $message): void
    {
        $branch = $this->branch;
        abort_if($branch === null, 404);

        $change($branch);
        unset($this->settings);

        Notification::make()->success()->title($message)->send();
    }
}
