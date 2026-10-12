<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Promotions;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Promotion;
use App\Filament\App\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\App\Resources\Promotions\Pages\EditPromotion;
use App\Filament\App\Resources\Promotions\Pages\ListPromotions;
use App\Filament\App\Resources\Promotions\RelationManagers\CouponsRelationManager;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use App\Support\Money;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $slug = 'promotions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasFeature(PlanFeature::Promotions);
    }

    public static function getModelLabel(): string
    {
        return __('promotions.admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('promotions.admin.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $currency = fn (): string => Workspace::restaurant()->currency;
        $is = fn (PromotionType ...$types): Closure => fn (Get $get): bool => in_array(self::type($get), $types, true);
        $scoped = [PromotionType::Percent, PromotionType::Fixed, PromotionType::Bogo];

        return $schema->columns(1)->components([
            Section::make(__('promotions.admin.sections.offer'))->schema([
                Bilingual::text('name', __('promotions.admin.fields.name'), maxLength: 60),
                ToggleButtons::make('type')->label(__('promotions.admin.fields.type'))->options(PromotionType::class)->default(PromotionType::Percent)->required()->inline()->live(),
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('percent')->label(__('promotions.admin.fields.percent'))->numeric()->minValue(1)->maxValue(100)->suffix('%')
                        ->required($is(PromotionType::Percent))->visible($is(PromotionType::Percent)),
                    TextInput::make('amount')->label(__('promotions.admin.fields.amount'))->numeric()->minValue(0.01)->step(0.01)->suffix($currency)
                        ->required($is(PromotionType::Fixed))->visible($is(PromotionType::Fixed)),
                    TextInput::make('buy_qty')->label(__('promotions.admin.fields.buy'))->numeric()->integer()->minValue(1)->maxValue(20)
                        ->required($is(PromotionType::Bogo))->visible($is(PromotionType::Bogo)),
                    TextInput::make('get_qty')->label(__('promotions.admin.fields.get'))->numeric()->integer()->minValue(1)->maxValue(20)
                        ->required($is(PromotionType::Bogo))->visible($is(PromotionType::Bogo)),
                    Select::make('free_product_id')->label(__('promotions.admin.fields.free_product'))->options(fn (): array => self::products())->searchable()
                        ->required($is(PromotionType::FreeItem))->visible($is(PromotionType::FreeItem)),
                    Select::make('combo_product_ids')->label(__('promotions.admin.fields.combo_products'))->options(fn (): array => self::products())->multiple()->searchable()->minItems(2)
                        ->required($is(PromotionType::ComboPrice))->visible($is(PromotionType::ComboPrice)),
                    TextInput::make('combo_price')->label(__('promotions.admin.fields.combo_price'))->numeric()->minValue(0)->step(0.01)->suffix($currency)
                        ->required($is(PromotionType::ComboPrice))->visible($is(PromotionType::ComboPrice)),
                    TextInput::make('bonus_points')->label(__('promotions.admin.fields.bonus_points'))->numeric()->integer()->minValue(1)
                        ->required($is(PromotionType::BonusPoints))->visible($is(PromotionType::BonusPoints)),
                ]),
                Radio::make('scope')->label(__('promotions.admin.fields.scope'))->options([
                    'all' => __('promotions.admin.scopes.all'),
                    'categories' => __('promotions.admin.scopes.categories'),
                    'products' => __('promotions.admin.scopes.products'),
                ])->default('all')->inline()->live()->visible($is(...$scoped)),
                Select::make('scope_ids')->label(__('promotions.admin.fields.scope_items'))->multiple()->searchable()
                    ->options(fn (Get $get): array => $get('scope') === 'categories'
                        ? MenuCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->name])->all()
                        : self::products())
                    ->required(fn (Get $get): bool => in_array(self::type($get), $scoped, true) && in_array($get('scope'), ['categories', 'products'], true))
                    ->visible(fn (Get $get): bool => in_array(self::type($get), $scoped, true) && in_array($get('scope'), ['categories', 'products'], true)),
            ]),
            Section::make(__('promotions.admin.sections.when'))
                ->description(__('promotions.admin.sections.when_hint'))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('branch_ids')->label(__('promotions.admin.fields.branches'))->helperText(__('promotions.admin.fields.branches_hint'))
                        ->options(fn (): array => Branch::query()->orderBy('id')->get()->mapWithKeys(fn (Branch $b): array => [$b->id => $b->name])->all())
                        ->multiple()->columnSpanFull(),
                    DateTimePicker::make('starts_at')->label(__('promotions.admin.fields.starts_at'))->seconds(false)->native(false),
                    DateTimePicker::make('ends_at')->label(__('promotions.admin.fields.ends_at'))->seconds(false)->native(false)->after('starts_at'),
                    CheckboxList::make('days')->label(__('promotions.admin.fields.days'))->helperText(__('promotions.admin.fields.days_hint'))
                        ->options(self::weekdays())->columns(['default' => 2, 'sm' => 4])->columnSpanFull(),
                    // Wall-clock hours in the restaurant's own time zone: never shifted.
                    TimePicker::make('time_from')->label(__('promotions.admin.fields.time_from'))->seconds(false)->timezone('UTC')->requiredWith('time_to'),
                    TimePicker::make('time_to')->label(__('promotions.admin.fields.time_to'))->helperText(__('promotions.admin.fields.time_hint'))->seconds(false)->timezone('UTC')->requiredWith('time_from'),
                    Toggle::make('first_order')->label(__('promotions.admin.fields.first_order')),
                    Toggle::make('birthday')->label(__('promotions.admin.fields.birthday'))->helperText(__('promotions.admin.fields.birthday_hint')),
                    TextInput::make('min_subtotal')->label(__('promotions.admin.fields.min_subtotal'))->numeric()->minValue(0)->step(0.01)->suffix($currency),
                ]),
            Section::make(__('promotions.admin.sections.rules'))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Toggle::make('requires_coupon')->label(__('promotions.admin.fields.requires_coupon'))->helperText(__('promotions.admin.fields.requires_coupon_hint')),
                    Toggle::make('stackable')->label(__('promotions.admin.fields.stackable'))->helperText(__('promotions.admin.fields.stackable_hint')),
                    TextInput::make('usage_limit')->label(__('promotions.admin.fields.usage_limit'))->numeric()->integer()->minValue(1),
                    TextInput::make('per_customer_limit')->label(__('promotions.admin.fields.per_customer_limit'))->numeric()->integer()->minValue(1),
                    TextInput::make('priority')->label(__('promotions.admin.fields.priority'))->helperText(__('promotions.admin.fields.priority_hint'))->numeric()->integer()->minValue(-100)->maxValue(100)->default(0),
                ]),
        ]);
    }

    /**
     * Form values → model attributes (money in minor units, percent in basis points).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        $currency = Workspace::restaurant()->currency;
        $type = $data['type'] instanceof PromotionType ? $data['type'] : PromotionType::from((string) $data['type']);
        $scoped = in_array($type, [PromotionType::Percent, PromotionType::Fixed, PromotionType::Bogo], true);
        $scope = $scoped ? (string) ($data['scope'] ?? 'all') : 'all';
        $ints = fn (mixed $ids): ?array => is_array($ids) && $ids !== [] ? array_values(array_map('intval', $ids)) : null;
        $time = fn (mixed $value): ?string => is_string($value) && preg_match('/^\d{2}:\d{2}/', $value) === 1 ? substr($value, 0, 5) : null;

        return [
            'name' => array_filter((array) $data['name'], 'filled'),
            'type' => $type,
            'value_bp' => $type === PromotionType::Percent ? (int) round(((float) $data['percent']) * 100) : null,
            'value_minor' => match ($type) {
                PromotionType::Fixed => Money::toMinor((string) $data['amount'], $currency),
                PromotionType::BonusPoints => (int) $data['bonus_points'],
                default => null,
            },
            'buy_qty' => $type === PromotionType::Bogo ? (int) $data['buy_qty'] : null,
            'get_qty' => $type === PromotionType::Bogo ? (int) $data['get_qty'] : null,
            'free_product_id' => $type === PromotionType::FreeItem ? (int) $data['free_product_id'] : null,
            'combo_product_ids' => $type === PromotionType::ComboPrice ? $ints($data['combo_product_ids'] ?? null) : null,
            'combo_price_minor' => $type === PromotionType::ComboPrice ? Money::toMinor((string) $data['combo_price'], $currency) : null,
            'scope' => $scope,
            'scope_ids' => $scope === 'all' ? null : $ints($data['scope_ids'] ?? null),
            'branch_ids' => $ints($data['branch_ids'] ?? null),
            'first_order' => (bool) ($data['first_order'] ?? false),
            'birthday' => (bool) ($data['birthday'] ?? false),
            'days' => $ints($data['days'] ?? null),
            'time_from' => $time($data['time_from'] ?? null),
            'time_to' => $time($data['time_to'] ?? null),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'min_subtotal_minor' => filled($data['min_subtotal'] ?? null) ? Money::toMinor((string) $data['min_subtotal'], $currency) : 0,
            'requires_coupon' => (bool) ($data['requires_coupon'] ?? false),
            'stackable' => (bool) ($data['stackable'] ?? false),
            'priority' => (int) ($data['priority'] ?? 0),
            'usage_limit' => filled($data['usage_limit'] ?? null) ? (int) $data['usage_limit'] : null,
            'per_customer_limit' => filled($data['per_customer_limit'] ?? null) ? (int) $data['per_customer_limit'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formData(Promotion $promotion): array
    {
        $currency = Workspace::restaurant()->currency;
        $major = fn (?int $minor): ?string => $minor === null ? null : Money::toMajor($minor, $currency);

        return [
            'name' => $promotion->getTranslations('name'),
            'type' => $promotion->type,
            'percent' => $promotion->value_bp === null ? null : $promotion->value_bp / 100,
            'amount' => $promotion->type === PromotionType::Fixed ? $major($promotion->value_minor) : null,
            'bonus_points' => $promotion->type === PromotionType::BonusPoints ? $promotion->value_minor : null,
            'buy_qty' => $promotion->buy_qty,
            'get_qty' => $promotion->get_qty,
            'free_product_id' => $promotion->free_product_id,
            'combo_product_ids' => $promotion->combo_product_ids,
            'combo_price' => $major($promotion->combo_price_minor),
            'scope' => $promotion->scope,
            'scope_ids' => $promotion->scope_ids,
            'branch_ids' => $promotion->branch_ids,
            'first_order' => $promotion->first_order,
            'birthday' => $promotion->birthday,
            'days' => $promotion->days,
            'time_from' => $promotion->time_from,
            'time_to' => $promotion->time_to,
            'starts_at' => $promotion->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $promotion->ends_at?->format('Y-m-d H:i:s'),
            'min_subtotal' => $promotion->min_subtotal_minor > 0 ? $major($promotion->min_subtotal_minor) : null,
            'requires_coupon' => $promotion->requires_coupon,
            'stackable' => $promotion->stackable,
            'priority' => $promotion->priority,
            'usage_limit' => $promotion->usage_limit,
            'per_customer_limit' => $promotion->per_customer_limit,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('coupons'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('promotions.admin.fields.name'))->weight('semibold')
                    ->description(fn (Promotion $record): string => $record->type->getLabel().($record->requires_coupon ? ' · '.trans_choice('promotions.admin.codes_count', $record->getAttribute('coupons_count'), ['count' => $record->getAttribute('coupons_count')]) : ''))
                    ->searchable(),
                TextColumn::make('status')->label(__('promotions.admin.fields.status'))
                    ->state(fn (Promotion $record): string => self::status($record))
                    ->formatStateUsing(fn (string $state): string => __('promotions.admin.status.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        'used_up' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('used_count')->label(__('promotions.admin.fields.used'))
                    ->formatStateUsing(fn (Promotion $record): string => $record->usage_limit === null ? number_format($record->used_count) : number_format($record->used_count).' / '.number_format($record->usage_limit)),
                TextColumn::make('ends_at')->label(__('promotions.admin.fields.ends_at'))->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('archived')->label(__('promotions.admin.fields.archived'))->default(false)
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('archived_at'), false: fn (Builder $query) => $query->whereNull('archived_at')),
            ])
            ->recordUrl(fn (Promotion $record): string => self::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
                Action::make('archive')
                    ->label(__('promotions.admin.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('promotions.admin.archive_description'))
                    ->visible(fn (Promotion $record): bool => $record->archived_at === null)
                    ->authorize(fn (Promotion $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->action(function (Promotion $record): void {
                        $record->forceFill(['archived_at' => now()])->save();
                        activity()->performedOn($record)->causedBy(Workspace::user())->event('promotion.archived')->log('promotion.archived');
                        Notification::make()->success()->title(__('promotions.admin.archived'))->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading(__('promotions.admin.empty.heading'))
            ->emptyStateDescription(__('promotions.admin.empty.description'));
    }

    public static function status(Promotion $promotion): string
    {
        return match (true) {
            $promotion->archived_at !== null => 'archived',
            $promotion->ends_at !== null && $promotion->ends_at->isPast() => 'ended',
            $promotion->starts_at !== null && $promotion->starts_at->isFuture() => 'scheduled',
            $promotion->usage_limit !== null && $promotion->used_count >= $promotion->usage_limit => 'used_up',
            default => 'live',
        };
    }

    public static function getRelations(): array
    {
        return [CouponsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function products(): array
    {
        return Product::query()->orderBy('sort')->get()->mapWithKeys(fn (Product $p): array => [$p->id => $p->name])->all();
    }

    /**
     * @return array<int, string>
     */
    private static function weekdays(): array
    {
        return collect([6, 0, 1, 2, 3, 4, 5])->mapWithKeys(fn (int $day): array => [$day => __('promotions.admin.days.'.$day)])->all();
    }

    private static function type(Get $get): ?PromotionType
    {
        $type = $get('type');

        return $type instanceof PromotionType ? $type : PromotionType::tryFrom((string) $type);
    }
}
