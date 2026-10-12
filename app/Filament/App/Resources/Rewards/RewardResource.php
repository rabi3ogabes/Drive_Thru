<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Rewards;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Menu\Models\Product;
use App\Filament\App\Resources\Rewards\Pages\ManageRewards;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RewardResource extends Resource
{
    protected static ?string $model = Reward::class;

    protected static ?string $slug = 'rewards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasFeature(PlanFeature::Loyalty);
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.rewards.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.rewards.plural');
    }

    /**
     * @return array<Component>
     */
    public static function formSchema(): array
    {
        $currency = fn (): string => Workspace::restaurant()->currency;

        return [
            Bilingual::text('name', __('loyalty.rewards.fields.name'), maxLength: 60),
            Bilingual::textarea('description', __('loyalty.rewards.fields.description'), maxLength: 200),
            ToggleButtons::make('type')
                ->label(__('loyalty.rewards.fields.type'))
                ->options(RewardType::class)
                ->default(RewardType::FreeItem)
                ->required()
                ->inline()
                ->live(),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('product_id')
                    ->label(__('loyalty.rewards.fields.product'))
                    ->options(fn (): array => Product::query()->orderBy('sort')->get()->mapWithKeys(fn (Product $p): array => [$p->id => $p->name])->all())
                    ->searchable()
                    ->required(fn (Get $get): bool => self::type($get) === RewardType::FreeItem)
                    ->visible(fn (Get $get): bool => self::type($get) === RewardType::FreeItem),
                TextInput::make('percent')
                    ->label(__('loyalty.rewards.fields.percent'))
                    ->numeric()->minValue(1)->maxValue(100)->suffix('%')
                    ->required(fn (Get $get): bool => self::type($get) === RewardType::PercentOff)
                    ->visible(fn (Get $get): bool => self::type($get) === RewardType::PercentOff),
                TextInput::make('amount')
                    ->label(__('loyalty.rewards.fields.amount'))
                    ->numeric()->minValue(0.01)->step(0.01)->suffix($currency)
                    ->required(fn (Get $get): bool => self::type($get) === RewardType::FixedOff)
                    ->visible(fn (Get $get): bool => self::type($get) === RewardType::FixedOff),
                TextInput::make('bonus')
                    ->label(__('loyalty.rewards.fields.bonus'))
                    ->helperText(__('loyalty.rewards.fields.bonus_hint'))
                    ->numeric()->integer()->minValue(1)
                    ->required(fn (Get $get): bool => self::type($get) === RewardType::BonusPoints)
                    ->visible(fn (Get $get): bool => self::type($get) === RewardType::BonusPoints),
                TextInput::make('points_cost')
                    ->label(__('loyalty.rewards.fields.cost'))
                    ->helperText(__('loyalty.rewards.fields.cost_hint'))
                    ->numeric()->integer()->minValue(1)->maxValue(10_000_000)
                    ->required(fn (Get $get): bool => self::type($get) !== RewardType::BonusPoints)
                    ->hidden(fn (Get $get): bool => self::type($get) === RewardType::BonusPoints),
                TextInput::make('min_order')
                    ->label(__('loyalty.rewards.fields.min_order'))
                    ->numeric()->minValue(0)->step(0.01)->suffix($currency),
                TextInput::make('valid_days')
                    ->label(__('loyalty.rewards.fields.valid_days'))
                    ->numeric()->integer()->minValue(1)->maxValue(365)->default(30)->required()->suffix(__('loyalty.admin.units.days')),
                TextInput::make('stock')
                    ->label(__('loyalty.rewards.fields.stock'))
                    ->helperText(__('loyalty.rewards.fields.stock_hint'))
                    ->numeric()->integer()->minValue(0),
            ]),
            Toggle::make('is_active')->label(__('loyalty.rewards.fields.active'))->default(true),
        ];
    }

    /**
     * Form values → model attributes (money to minor units, percent to basis points).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        $currency = Workspace::restaurant()->currency;
        $type = $data['type'] instanceof RewardType ? $data['type'] : RewardType::from((string) $data['type']);

        return [
            'name' => array_filter((array) $data['name'], 'filled'),
            'description' => array_filter((array) ($data['description'] ?? []), 'filled') ?: null,
            'type' => $type,
            'product_id' => $type === RewardType::FreeItem ? (int) $data['product_id'] : null,
            'value' => match ($type) {
                RewardType::PercentOff => (int) round(((float) $data['percent']) * 100),
                RewardType::FixedOff => Money::toMinor((string) $data['amount'], $currency),
                RewardType::BonusPoints => (int) $data['bonus'],
                RewardType::FreeItem => null,
            },
            'points_cost' => $type === RewardType::BonusPoints ? null : (int) $data['points_cost'],
            'min_order_minor' => filled($data['min_order'] ?? null) ? Money::toMinor((string) $data['min_order'], $currency) : 0,
            'valid_days' => (int) $data['valid_days'],
            'stock' => filled($data['stock'] ?? null) ? (int) $data['stock'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formData(Reward $reward): array
    {
        $currency = Workspace::restaurant()->currency;

        return [
            'name' => $reward->getTranslations('name'),
            'description' => $reward->getTranslations('description'),
            'type' => $reward->type,
            'product_id' => $reward->product_id,
            'percent' => $reward->type === RewardType::PercentOff ? ($reward->value ?? 0) / 100 : null,
            'amount' => $reward->type === RewardType::FixedOff ? Money::toMajor((int) $reward->value, $currency) : null,
            'bonus' => $reward->type === RewardType::BonusPoints ? $reward->value : null,
            'points_cost' => $reward->points_cost,
            'min_order' => $reward->min_order_minor > 0 ? Money::toMajor($reward->min_order_minor, $currency) : null,
            'valid_days' => $reward->valid_days,
            'stock' => $reward->stock,
            'is_active' => $reward->is_active,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('archived_at')->withCount(['wallet as claimed_count']))
            ->defaultSort('points_cost')
            ->columns([
                TextColumn::make('name')->label(__('loyalty.rewards.fields.name'))->weight('semibold')
                    ->description(fn (Reward $record): string => $record->type->getLabel())->searchable(),
                TextColumn::make('points_cost')->label(__('loyalty.rewards.fields.cost'))->numeric()->placeholder(__('loyalty.rewards.given_only'))->sortable(),
                TextColumn::make('stock')->label(__('loyalty.rewards.fields.stock'))->numeric()->placeholder('∞'),
                TextColumn::make('claimed_count')->label(__('loyalty.rewards.claimed'))->numeric(),
                ToggleColumn::make('is_active')->label(__('loyalty.rewards.fields.active'))
                    ->disabled(fn (): bool => ! (auth()->user()?->can('create', Reward::class) ?? false)),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('loyalty.rewards.fields.active'))])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->fillForm(fn (Reward $record): array => self::formData($record))
                        ->schema(self::formSchema())
                        ->using(function (Reward $record, array $data): Reward {
                            $record->update(self::attributes($data));
                            activity()->performedOn($record)->causedBy(Workspace::user())->event('loyalty.reward_updated')->log('loyalty.reward_updated');

                            return $record;
                        }),
                    Action::make('archive')
                        ->label(__('loyalty.rewards.archive'))
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('loyalty.rewards.archive_description'))
                        ->authorize(fn (Reward $record): bool => auth()->user()?->can('delete', $record) ?? false)
                        ->action(function (Reward $record): void {
                            $record->forceFill(['archived_at' => now(), 'is_active' => false])->save();
                            activity()->performedOn($record)->causedBy(Workspace::user())->event('loyalty.reward_archived')->log('loyalty.reward_archived');
                            Notification::make()->success()->title(__('loyalty.rewards.archived'))->send();
                        }),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-gift')
            ->emptyStateHeading(__('loyalty.rewards.empty.heading'))
            ->emptyStateDescription(__('loyalty.rewards.empty.description'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageRewards::route('/')];
    }

    private static function type(Get $get): ?RewardType
    {
        $type = $get('type');

        return $type instanceof RewardType ? $type : RewardType::tryFrom((string) $type);
    }
}
