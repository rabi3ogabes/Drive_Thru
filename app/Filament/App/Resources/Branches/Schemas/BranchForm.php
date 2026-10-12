<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Branches\Schemas;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Enums\LaneKind;
use App\Domain\Branches\Enums\StationKind;
use App\Domain\Branches\Models\BranchClosure;
use App\Domain\Branches\Models\Lane;
use App\Domain\Branches\Models\Station;
use App\Filament\App\Resources\Branches\BranchResource;
use App\Filament\App\Workspace;
use App\Filament\Support\Bilingual;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * The branch editor, in four calm tabs: details, opening hours, pickup and
 * kitchen, and arrival tracking.
 */
final class BranchForm
{
    /**
     * @return array<Component>
     */
    public static function full(): array
    {
        return [
            Tabs::make()
                ->contained(false)
                ->tabs([
                    Tab::make(__('branches.tabs.details'))->icon('heroicon-o-building-storefront')->schema(self::details()),
                    Tab::make(__('branches.tabs.hours'))->icon('heroicon-o-clock')->schema(self::hours()),
                    Tab::make(__('branches.tabs.service'))->icon('heroicon-o-arrows-right-left')->schema(self::service()),
                    Tab::make(__('branches.tabs.tracking'))->icon('heroicon-o-map-pin')->schema(self::tracking()),
                    Tab::make(__('branches.tabs.printing'))->icon('heroicon-o-printer')->schema(self::printing())->hiddenOn('create'),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function details(): array
    {
        return [
            Bilingual::text('name', __('branches.fields.name'), maxLength: 80),
            TextInput::make('code')
                ->label(__('branches.fields.code'))
                ->helperText(__('branches.fields.code_hint'))
                ->prefix(fn (): string => Str::after(url('/'.Workspace::restaurant()->slug), '://').'/')
                ->maxLength(40)
                ->alphaDash()
                ->notIn(CreateBranch::RESERVED_CODES)
                ->unique(table: 'branches', column: 'code', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => BranchResource::uniqueCodeRule($rule))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null)
                ->extraInputAttributes(['dir' => 'ltr']),
            Bilingual::text('city', __('branches.fields.city'), required: false, maxLength: 80),
            Bilingual::text('address', __('branches.fields.address'), required: false, maxLength: 200),
            TagsInput::make('phones')
                ->label(__('branches.fields.phones'))
                ->placeholder(__('branches.fields.phones_placeholder'))
                ->helperText(__('branches.fields.phones_hint'))
                ->nestedRecursiveRules(['regex:/^\+?[0-9 ()-]{6,20}$/'])
                ->reorderable(),
            TextInput::make('maps_url')
                ->label(__('branches.fields.maps_url'))
                ->url()
                ->maxLength(500)
                ->extraInputAttributes(['dir' => 'ltr']),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('latitude')
                    ->label(__('branches.fields.latitude'))
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90)
                    ->requiredWith('longitude')
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->hintAction(
                        Action::make('locate')
                            ->label(__('branches.actions.use_location'))
                            ->icon('heroicon-m-map-pin')
                            ->alpineClickHandler(function (TextInput $component): string {
                                $lat = $component->getStatePath() ?? 'latitude';
                                $lng = Str::beforeLast($lat, '.').'.longitude';
                                // Js::from() is safe inside an HTML attribute (no raw double quotes).
                                $error = Js::from(__('branches.notifications.location_failed'));

                                return <<<JS
                                    if (! navigator.geolocation) { alert({$error}); return; }
                                    navigator.geolocation.getCurrentPosition(
                                        (p) => { \$wire.set('{$lat}', p.coords.latitude.toFixed(7)); \$wire.set('{$lng}', p.coords.longitude.toFixed(7)); },
                                        () => alert({$error}),
                                        { enableHighAccuracy: true, timeout: 10000 },
                                    )
                                JS;
                            }),
                    ),
                TextInput::make('longitude')
                    ->label(__('branches.fields.longitude'))
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180)
                    ->requiredWith('latitude')
                    ->extraInputAttributes(['dir' => 'ltr']),
            ]),
            Section::make(__('branches.sections.logo'))
                ->compact()
                ->schema([
                    Toggle::make('use_restaurant_logo')
                        ->label(__('branches.fields.use_restaurant_logo'))
                        ->default(true)
                        ->live(),
                    FileUpload::make('logo_path')
                        ->label(__('branches.fields.logo'))
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory(fn (): string => 'restaurants/'.Workspace::restaurant()->id.'/branches')
                        ->visibility('public')
                        ->maxSize(3072)
                        ->hidden(fn (Get $get): bool => (bool) $get('use_restaurant_logo')),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function hours(): array
    {
        return [
            Repeater::make('hours')
                ->relationship()
                ->label(__('branches.fields.weekly_hours'))
                ->helperText(__('branches.fields.weekly_hours_hint'))
                ->table([
                    TableColumn::make(__('branches.fields.day'))->markAsRequired(),
                    TableColumn::make(__('branches.fields.opens'))->markAsRequired(),
                    TableColumn::make(__('branches.fields.closes'))->markAsRequired(),
                ])
                ->schema([
                    Select::make('weekday')
                        ->options(self::weekdays())
                        ->required()
                        ->native(false),
                    // Wall-clock times in the restaurant's own zone: never shift them.
                    TimePicker::make('opens_at')->seconds(false)->timezone('UTC')->required(),
                    TimePicker::make('closes_at')->seconds(false)->timezone('UTC')->required(),
                ])
                ->reorderable(false)
                ->defaultItems(0)
                ->addActionLabel(__('branches.actions.add_shift'))
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['restaurant_id' => Workspace::restaurant()->id])
                ->hintAction(
                    Action::make('standardWeek')
                        ->label(__('branches.actions.standard_week'))
                        ->icon('heroicon-m-sparkles')
                        ->action(function (Set $set): void {
                            $set('hours', collect(range(0, 6))
                                ->mapWithKeys(fn (int $day): array => [(string) Str::uuid() => ['weekday' => $day, 'opens_at' => '07:00', 'closes_at' => '23:00']])
                                ->all());
                        }),
                ),
            Repeater::make('closures')
                ->relationship()
                ->label(__('branches.fields.closures'))
                ->helperText(__('branches.fields.closures_hint'))
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        DatePicker::make('starts_on')->label(__('branches.fields.starts_on'))->native(false)->required()->live(),
                        DatePicker::make('ends_on')->label(__('branches.fields.ends_on'))->native(false)->required()
                            ->afterOrEqual('starts_on'),
                    ]),
                    Bilingual::text('reason', __('branches.fields.reason'), required: false, maxLength: 120),
                ])
                ->defaultItems(0)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => filled($state['starts_on'] ?? null) ? ($state['starts_on'].(($state['ends_on'] ?? null) && $state['ends_on'] !== $state['starts_on'] ? ' → '.$state['ends_on'] : '')) : null)
                ->addActionLabel(__('branches.actions.add_closure'))
                ->mutateRelationshipDataBeforeFillUsing(Bilingual::fillRelated(BranchClosure::class, ['reason']))
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['restaurant_id' => Workspace::restaurant()->id]),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function service(): array
    {
        $stations = Workspace::restaurant()->hasFeature(PlanFeature::KitchenStations);

        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('avg_prep_minutes')
                    ->label(__('branches.fields.avg_prep_minutes'))
                    ->helperText(__('branches.fields.avg_prep_minutes_hint'))
                    ->numeric()->integer()->minValue(1)->maxValue(120)->required()
                    ->suffix(__('branches.units.minutes')),
                TextInput::make('capacity_per_slot')
                    ->label(__('branches.fields.capacity_per_slot'))
                    ->helperText(__('branches.fields.capacity_per_slot_hint'))
                    ->numeric()->integer()->minValue(1)->maxValue(500)
                    ->placeholder(__('branches.fields.unlimited'))
                    ->suffix(__('branches.units.orders')),
            ]),
            Repeater::make('lanes')
                ->relationship()
                ->label(__('branches.fields.lanes'))
                ->hiddenOn('create')
                ->helperText(__('branches.fields.lanes_hint'))
                ->schema([
                    Bilingual::text('name', __('branches.fields.lane_name'), maxLength: 40),
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        ToggleButtons::make('kind')->label(__('branches.fields.lane_kind'))->options(LaneKind::class)->default(LaneKind::Lane)->inline()->required(),
                        Toggle::make('is_active')->label(__('branches.fields.active'))->default(true)->inline(false),
                    ]),
                ])
                ->orderColumn('sort')
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'][app()->getLocale()] ?? $state['name']['en'] ?? null)
                ->addActionLabel(__('branches.actions.add_lane'))
                ->mutateRelationshipDataBeforeFillUsing(Bilingual::fillRelated(Lane::class, ['name']))
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['restaurant_id' => Workspace::restaurant()->id]),
            Repeater::make('stations')
                ->relationship()
                ->label(__('branches.fields.stations'))
                ->hiddenOn('create')
                ->helperText($stations ? __('branches.fields.stations_hint') : __('branches.fields.stations_locked'))
                ->schema([
                    Bilingual::text('name', __('branches.fields.station_name'), maxLength: 40),
                    Select::make('kind')
                        ->label(__('branches.fields.station_kind'))
                        ->helperText(__('branches.fields.station_kind_hint'))
                        ->options(StationKind::class)
                        ->default(StationKind::General->value)
                        ->required()
                        ->native(false),
                    Toggle::make('is_active')->label(__('branches.fields.active'))->default(true),
                ])
                ->disabled(! $stations)
                ->orderColumn('sort')
                ->reorderableWithButtons()
                ->collapsible()
                ->defaultItems(0)
                ->itemLabel(fn (array $state): ?string => $state['name'][app()->getLocale()] ?? $state['name']['en'] ?? null)
                ->addActionLabel(__('branches.actions.add_station'))
                ->mutateRelationshipDataBeforeFillUsing(Bilingual::fillRelated(Station::class, ['name']))
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['restaurant_id' => Workspace::restaurant()->id]),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function tracking(): array
    {
        $enabled = Workspace::restaurant()->hasFeature(PlanFeature::ArrivalTracking);

        return [
            Toggle::make('tracking_enabled')
                ->label(__('branches.fields.tracking_enabled'))
                ->helperText($enabled ? __('branches.fields.tracking_enabled_hint') : __('branches.fields.tracking_locked'))
                ->disabled(! $enabled)
                ->live(),
            Grid::make(['default' => 1, 'sm' => 2])
                ->visible(fn (Get $get): bool => $enabled && (bool) $get('tracking_enabled'))
                ->schema([
                    TextInput::make('arrival_radius_m')->label(__('branches.fields.arrival_radius_m'))->helperText(__('branches.fields.arrival_radius_m_hint'))
                        ->numeric()->integer()->minValue(10)->maxValue(500)->required()->suffix(__('branches.units.meters')),
                    TextInput::make('approach_radius_m')->label(__('branches.fields.approach_radius_m'))->helperText(__('branches.fields.approach_radius_m_hint'))
                        ->numeric()->integer()->minValue(100)->maxValue(10000)->required()->gt('arrival_radius_m')->suffix(__('branches.units.meters')),
                    TextInput::make('avg_speed_kmh')->label(__('branches.fields.avg_speed_kmh'))->helperText(__('branches.fields.avg_speed_kmh_hint'))
                        ->numeric()->integer()->minValue(5)->maxValue(120)->required()->suffix(__('branches.units.kmh')),
                    TextInput::make('location_ping_seconds')->label(__('branches.fields.location_ping_seconds'))->helperText(__('branches.fields.location_ping_seconds_hint'))
                        ->numeric()->integer()->minValue(5)->maxValue(120)->required()->suffix(__('branches.units.seconds')),
                    Toggle::make('auto_arrival')->label(__('branches.fields.auto_arrival'))->helperText(__('branches.fields.auto_arrival_hint'))->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Branch printers (PrintNode printer ids) and automatic kitchen tickets.
     *
     * @return array<Component>
     */
    public static function printing(): array
    {
        return [
            Section::make(__('branches.sections.printing'))
                ->description(__('branches.sections.printing_hint'))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('printers.kitchen')->label(__('branches.fields.printer_kitchen'))->helperText(__('branches.fields.printer_hint'))->numeric()->maxLength(20),
                    TextInput::make('printers.receipt')->label(__('branches.fields.printer_receipt'))->helperText(__('branches.fields.printer_hint'))->numeric()->maxLength(20),
                    Toggle::make('auto_print')->label(__('branches.fields.auto_print'))->helperText(__('branches.fields.auto_print_hint'))->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Weekday options, Sunday first (Carbon's numbering).
     *
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        return collect(range(0, 6))
            ->mapWithKeys(fn (int $day): array => [$day => now()->startOfWeek(0)->addDays($day)->translatedFormat('l')])
            ->all();
    }
}
