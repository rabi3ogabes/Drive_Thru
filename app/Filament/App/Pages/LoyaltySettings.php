<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Exceptions\NotEnoughPoints;
use App\Domain\Loyalty\Models\EarnRule;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\Tiers;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use App\Support\Lang;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * The owner's loyalty program: what points are worth (no defaults: it stays
 * off until every value is set), tiers, and earn boosts. Plus adjusting a
 * guest's points by hand, with a reason, in the audit log.
 *
 * @property-read Schema $form
 */
class LoyaltySettings extends Page
{
    protected static ?string $slug = 'loyalty';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Workspace::can(RestaurantPermission::ManageLoyalty) && Workspace::restaurant()->hasFeature(PlanFeature::Loyalty);
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty.admin.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('loyalty.admin.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('loyalty.admin.subheading');
    }

    public function mount(): void
    {
        $restaurant = Workspace::restaurant();
        $program = $this->program();
        $currency = $restaurant->currency;

        $this->form->fill([
            'is_enabled' => $program->is_enabled,
            'earn_points_per_unit' => $program->earn_points_per_unit,
            'redeem_points' => $program->redeem_points,
            'redeem_value' => $program->redeem_value_minor === null ? null : Money::toMajor($program->redeem_value_minor, $currency),
            'max_redeem_percent' => $program->max_redeem_percent,
            'min_redeem_points' => $program->min_redeem_points,
            'expiry_months' => $program->expiry_months,
            'tier_window_months' => $program->tier_window_months,
            'tiers' => Tier::query()->orderBy('threshold_points')->orderBy('sort')->get()->map(fn (Tier $t): array => [
                'id' => $t->id,
                'name' => $t->getTranslations('name'),
                'threshold_points' => $t->threshold_points,
                'multiplier' => $t->earn_multiplier_bp / 10_000,
                'benefits' => $t->getTranslations('benefits'),
            ])->all(),
            'rules' => EarnRule::query()->orderBy('id')->get()->map(fn (EarnRule $r): array => [
                'id' => $r->id,
                'scope' => $r->scope,
                'scope_id' => $r->scope_id,
                'multiplier' => $r->multiplier_bp / 10_000,
                'starts_at' => $r->starts_at?->format('Y-m-d H:i:s'),
                'ends_at' => $r->ends_at?->format('Y-m-d H:i:s'),
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $currency = Workspace::restaurant()->currency;
        $whenOn = fn (Get $get): bool => (bool) $get('is_enabled');

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->persistTabInQueryString()->tabs([
                    Tab::make(__('loyalty.admin.tabs.program'))
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Section::make(__('loyalty.admin.sections.program'))
                                ->description(__('loyalty.admin.sections.program_hint'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    Toggle::make('is_enabled')->label(__('loyalty.admin.fields.enabled'))->helperText(__('loyalty.admin.fields.enabled_hint'))->live()->columnSpanFull(),
                                    TextInput::make('earn_points_per_unit')->label(__('loyalty.admin.fields.earn', ['currency' => $currency]))->numeric()->integer()->minValue(1)->maxValue(1000)->required($whenOn)->live(onBlur: true),
                                    TextInput::make('tier_window_months')->label(__('loyalty.admin.fields.tier_window'))->helperText(__('loyalty.admin.fields.tier_window_hint'))->numeric()->integer()->minValue(1)->maxValue(36)->required()->suffix(__('loyalty.admin.units.months')),
                                    TextInput::make('redeem_points')->label(__('loyalty.admin.fields.redeem_points'))->numeric()->integer()->minValue(1)->maxValue(100000)->required($whenOn)->live(onBlur: true),
                                    TextInput::make('redeem_value')->label(__('loyalty.admin.fields.redeem_value'))->numeric()->minValue(0.01)->maxValue(10000)->step(0.01)->required($whenOn)->suffix($currency)->live(onBlur: true),
                                    TextInput::make('max_redeem_percent')->label(__('loyalty.admin.fields.max_redeem'))->helperText(__('loyalty.admin.fields.max_redeem_hint'))->numeric()->integer()->minValue(1)->maxValue(100)->required($whenOn)->suffix('%'),
                                    TextInput::make('min_redeem_points')->label(__('loyalty.admin.fields.min_redeem'))->numeric()->integer()->minValue(1)->maxValue(1000000)->required($whenOn),
                                    TextInput::make('expiry_months')->label(__('loyalty.admin.fields.expiry'))->helperText(__('loyalty.admin.fields.expiry_hint'))->numeric()->integer()->minValue(1)->maxValue(60)->suffix(__('loyalty.admin.units.months')),
                                    TextEntry::make('example')->hiddenLabel()->columnSpanFull()
                                        ->state(fn (Get $get): string => $this->example($get, $currency)),
                                ]),
                        ]),
                    Tab::make(__('loyalty.admin.tabs.tiers'))
                        ->icon('heroicon-o-trophy')
                        ->schema([
                            Section::make(__('loyalty.admin.sections.tiers'))
                                ->description(__('loyalty.admin.sections.tiers_hint'))
                                ->headerActions([
                                    Action::make('standardTiers')
                                        ->label(__('loyalty.admin.actions.standard_tiers'))
                                        ->icon('heroicon-m-sparkles')
                                        ->visible(fn (Get $get): bool => ($get('tiers') ?? []) === [])
                                        ->action(function (): void {
                                            app(Tiers::class)->standardPreset($this->program());
                                            $this->mount();
                                            Notification::make()->success()->title(__('loyalty.admin.notifications.tiers_created'))->send();
                                        }),
                                ])
                                ->schema([
                                    Repeater::make('tiers')
                                        ->hiddenLabel()
                                        ->schema([
                                            Hidden::make('id'),
                                            Bilingual::text('name', __('loyalty.admin.fields.tier_name'), maxLength: 40),
                                            Grid::make(['default' => 1, 'sm' => 2])->schema([
                                                TextInput::make('threshold_points')->label(__('loyalty.admin.fields.threshold'))->numeric()->integer()->minValue(0)->maxValue(10_000_000)->required(),
                                                TextInput::make('multiplier')->label(__('loyalty.admin.fields.multiplier'))->helperText(__('loyalty.admin.fields.multiplier_hint'))->numeric()->minValue(1)->maxValue(10)->step(0.05)->default(1)->required()->prefix('×'),
                                            ]),
                                            Bilingual::textarea('benefits', __('loyalty.admin.fields.benefits'), maxLength: 300),
                                        ])
                                        ->defaultItems(0)
                                        ->addActionLabel(__('loyalty.admin.actions.add_tier'))
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => is_array($state['name'] ?? null) ? ($state['name'][app()->getLocale()] ?? null) : null),
                                ]),
                        ]),
                    Tab::make(__('loyalty.admin.tabs.boosts'))
                        ->icon('heroicon-o-bolt')
                        ->schema([
                            Section::make(__('loyalty.admin.sections.boosts'))
                                ->description(__('loyalty.admin.sections.boosts_hint'))
                                ->schema([
                                    Repeater::make('rules')
                                        ->hiddenLabel()
                                        ->schema([
                                            Hidden::make('id'),
                                            Grid::make(['default' => 1, 'sm' => 3])->schema([
                                                Select::make('scope')->label(__('loyalty.admin.fields.scope'))->options([
                                                    'product' => __('loyalty.admin.scopes.product'),
                                                    'category' => __('loyalty.admin.scopes.category'),
                                                    'branch' => __('loyalty.admin.scopes.branch'),
                                                ])->required()->live()->native(false),
                                                Select::make('scope_id')->label(__('loyalty.admin.fields.target'))
                                                    ->options(fn (Get $get): array => $this->targets((string) $get('scope')))
                                                    ->searchable()->required(),
                                                TextInput::make('multiplier')->label(__('loyalty.admin.fields.multiplier'))->numeric()->minValue(1.05)->maxValue(10)->step(0.05)->default(2)->required()->prefix('×'),
                                            ]),
                                            Grid::make(['default' => 1, 'sm' => 2])->schema([
                                                DateTimePicker::make('starts_at')->label(__('loyalty.admin.fields.starts_at'))->seconds(false)->native(false),
                                                DateTimePicker::make('ends_at')->label(__('loyalty.admin.fields.ends_at'))->seconds(false)->native(false)->after('starts_at'),
                                            ]),
                                        ])
                                        ->defaultItems(0)
                                        ->addActionLabel(__('loyalty.admin.actions.add_boost'))
                                        ->collapsible(),
                                ]),
                        ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('loyalty-settings')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('settings.save'))->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustPoints')
                ->label(__('loyalty.admin.actions.adjust'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading(__('loyalty.admin.adjust.heading'))
                ->modalDescription(__('loyalty.admin.adjust.description'))
                ->schema([
                    TextInput::make('phone')->label(__('loyalty.admin.adjust.phone'))->tel()->required()->maxLength(20)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('points')->label(__('loyalty.admin.adjust.points'))->helperText(__('loyalty.admin.adjust.points_hint'))->numeric()->integer()->required()->minValue(-1_000_000)->maxValue(1_000_000)->notIn([0]),
                    TextInput::make('reason')->label(__('loyalty.admin.adjust.reason'))->required()->minLength(3)->maxLength(150),
                ])
                ->action(function (array $data, Action $action): void {
                    $restaurant = Workspace::restaurant();
                    $phone = Phone::normalize((string) $data['phone']);
                    $customer = $phone === null ? null : Customer::query()->where('phone', $phone)->first();

                    if ($customer === null) {
                        Notification::make()->danger()->title(__('loyalty.admin.adjust.not_found'))->send();
                        $action->halt();

                        return;
                    }

                    $points = (int) $data['points'];
                    $reason = trim((string) $data['reason']);

                    try {
                        $points > 0
                            ? app(Points::class)->credit($customer, LedgerType::Adjust, $points, null, $reason, Workspace::user())
                            : app(Points::class)->debit($customer, LedgerType::Adjust, -$points, null, $reason, Workspace::user());
                    } catch (NotEnoughPoints) {
                        Notification::make()->danger()->title(__('loyalty.admin.adjust.not_enough', ['balance' => number_format($customer->points_balance)]))->send();
                        $action->halt();

                        return;
                    }

                    activity()->performedOn($customer)->causedBy(Workspace::user())->event('loyalty.points_adjusted')
                        ->withProperties(['points' => $points, 'reason' => $reason, 'restaurant_id' => $restaurant->id])->log('loyalty.points_adjusted');

                    Notification::make()->success()->title(__('loyalty.admin.adjust.done', ['balance' => number_format($customer->fresh()->points_balance ?? 0)]))->send();
                }),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $state = $this->form->getState();
        $restaurant = Workspace::restaurant();
        $program = $this->program();

        DB::transaction(function () use ($state, $restaurant, $program): void {
            $program->fill([
                'is_enabled' => (bool) ($state['is_enabled'] ?? false),
                'earn_points_per_unit' => $this->intOrNull($state['earn_points_per_unit'] ?? null),
                'redeem_points' => $this->intOrNull($state['redeem_points'] ?? null),
                'redeem_value_minor' => filled($state['redeem_value'] ?? null) ? Money::toMinor((string) $state['redeem_value'], $restaurant->currency) : null,
                'max_redeem_percent' => $this->intOrNull($state['max_redeem_percent'] ?? null),
                'min_redeem_points' => $this->intOrNull($state['min_redeem_points'] ?? null),
                'expiry_months' => $this->intOrNull($state['expiry_months'] ?? null),
                'tier_window_months' => (int) ($state['tier_window_months'] ?? 12),
            ])->save();

            $this->syncTiers((array) ($state['tiers'] ?? []), $restaurant->id);
            $this->syncRules((array) ($state['rules'] ?? []), $restaurant->id);

            activity()->performedOn($restaurant)->causedBy(Workspace::user())->event('loyalty.program_saved')
                ->withProperties(['program' => $program->only(['is_enabled', 'earn_points_per_unit', 'redeem_points', 'redeem_value_minor', 'max_redeem_percent', 'min_redeem_points', 'expiry_months'])])
                ->log('loyalty.program_saved');
        });

        $this->mount();

        $program->refresh()->is_enabled && ! $program->isActive()
            ? Notification::make()->warning()->title(__('loyalty.admin.notifications.incomplete'))->send()
            : Notification::make()->success()->title(__('loyalty.admin.notifications.saved'))->send();
    }

    private function program(): LoyaltyProgram
    {
        return LoyaltyProgram::query()->firstOrCreate(['restaurant_id' => Workspace::restaurant()->id]);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    private function syncTiers(array $rows, int $restaurantId): void
    {
        $keep = [];

        foreach (array_values($rows) as $sort => $row) {
            $tier = Tier::query()->whereKey((int) ($row['id'] ?? 0))->first() ?? new Tier(['restaurant_id' => $restaurantId]);
            $tier->fill([
                'name' => array_filter((array) ($row['name'] ?? []), 'filled'),
                'threshold_points' => (int) ($row['threshold_points'] ?? 0),
                'earn_multiplier_bp' => (int) round(((float) ($row['multiplier'] ?? 1)) * 10_000),
                'benefits' => array_filter((array) ($row['benefits'] ?? []), 'filled') ?: null,
                'sort' => $sort,
            ])->save();
            $keep[] = $tier->id;
        }

        Tier::query()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    private function syncRules(array $rows, int $restaurantId): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $scope = (string) ($row['scope'] ?? '');

            if (! in_array($scope, EarnRule::SCOPES, true) || ! array_key_exists((int) ($row['scope_id'] ?? 0), $this->targets($scope))) {
                continue;
            }

            $rule = EarnRule::query()->whereKey((int) ($row['id'] ?? 0))->first() ?? new EarnRule(['restaurant_id' => $restaurantId]);
            $rule->fill([
                'scope' => $scope,
                'scope_id' => (int) $row['scope_id'],
                'multiplier_bp' => (int) round(((float) ($row['multiplier'] ?? 1)) * 10_000),
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
            ])->save();
            $keep[] = $rule->id;
        }

        EarnRule::query()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @return array<int, string>
     */
    private function targets(string $scope): array
    {
        return match ($scope) {
            'product' => Product::query()->orderBy('sort')->get()->mapWithKeys(fn (Product $p): array => [$p->id => $p->name])->all(),
            'category' => MenuCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->name])->all(),
            'branch' => Branch::query()->orderBy('id')->get()->mapWithKeys(fn (Branch $b): array => [$b->id => $b->name])->all(),
            default => [],
        };
    }

    private function example(Get $get, string $currency): string
    {
        $earn = (int) $get('earn_points_per_unit');
        $redeemPoints = (int) $get('redeem_points');
        $redeemValue = (float) $get('redeem_value');

        if ($earn < 1 || $redeemPoints < 1 || $redeemValue <= 0) {
            return Lang::text('loyalty.admin.example_incomplete');
        }

        $order = 50;
        $earned = $order * $earn;
        $worth = $earned / $redeemPoints * $redeemValue;

        return Lang::text('loyalty.admin.example', [
            'order' => Money::format(Money::toMinor($order, $currency), $currency),
            'points' => number_format($earned),
            'worth' => Money::format(Money::toMinor(round($worth, 2), $currency), $currency),
            'percent' => number_format($worth / $order * 100, 1),
        ]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
