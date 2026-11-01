<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCampaigns;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Branches\Models\Branch;
use App\Domain\Campaigns\Models\QrCampaign;
use App\Filament\App\Resources\QrCampaigns\Pages\CreateQrCampaign;
use App\Filament\App\Resources\QrCampaigns\Pages\EditQrCampaign;
use App\Filament\App\Resources\QrCampaigns\Pages\ListQrCampaigns;
use App\Filament\App\Resources\QrCampaigns\Pages\ViewQrCampaign;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Tracked QR codes: one per place a code is printed, each with its own
 * funnel (scans → visits → orders → revenue) and print-ready posters.
 */
class QrCampaignResource extends Resource
{
    protected static ?string $model = QrCampaign::class;

    protected static ?string $slug = 'qr-codes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasFeature(PlanFeature::Campaigns);
    }

    public static function getModelLabel(): string
    {
        return __('qr.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('qr.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $tz = fn (): string => Workspace::restaurant()->timezone;

        return $schema->columns(1)->components([
            Section::make(__('qr.sections.where'))->description(__('qr.sections.where_hint'))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('name')->label(__('qr.fields.name'))->placeholder(__('qr.fields.name_placeholder'))->required()->maxLength(80),
                    TextInput::make('placement')->label(__('qr.fields.placement'))->helperText(__('qr.fields.placement_hint'))
                        ->datalist((array) __('qr.placements'))->maxLength(60),
                ]),
            Section::make(__('qr.sections.opens'))->description(__('qr.sections.opens_hint'))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('branch_id')->label(__('qr.fields.branch'))
                        ->placeholder(__('qr.fields.restaurant_page'))
                        ->options(fn (): array => Branch::query()->orderBy('id')->get()->mapWithKeys(fn (Branch $b): array => [$b->id => $b->name])->all())
                        ->live(),
                    TextInput::make('lane')->label(__('qr.fields.lane'))->helperText(__('qr.fields.lane_hint'))->maxLength(20)
                        ->visible(fn (Get $get): bool => filled($get('branch_id'))),
                    TextInput::make('promo')->label(__('qr.fields.promo'))->helperText(__('qr.fields.promo_hint'))->maxLength(40)->alphaDash(),
                ]),
            Section::make(__('qr.sections.when'))->description(__('qr.sections.when_hint'))
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    DateTimePicker::make('starts_at')->label(__('qr.fields.starts_at'))->seconds(false)->native(false)->timezone($tz),
                    DateTimePicker::make('ends_at')->label(__('qr.fields.ends_at'))->seconds(false)->native(false)->timezone($tz)->after('starts_at'),
                    Toggle::make('is_active')->label(__('qr.fields.is_active'))->default(true)->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('branch')->withCount([
                'scans' => fn (Builder $query) => $query->where('is_bot', false),
                'scans as visits_count' => fn (Builder $query) => $query->where('is_bot', false)->whereNotNull('visited_at'),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('qr.fields.name'))->weight('medium')->searchable()
                    ->description(fn (QrCampaign $record): ?string => $record->placement),
                TextColumn::make('branch.name')->label(__('qr.fields.branch'))->placeholder(__('qr.fields.restaurant_page'))->toggleable(),
                TextColumn::make('code')->label(__('qr.fields.code'))->fontFamily('mono')->copyable()->copyMessage(__('qr.copied')),
                TextColumn::make('status')->badge()
                    ->state(fn (QrCampaign $record): string => self::status($record))
                    ->formatStateUsing(fn (string $state): string => __('qr.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('scans_count')->label(__('qr.columns.scans'))->numeric()->alignEnd(),
                TextColumn::make('visits_count')->label(__('qr.columns.visits'))->numeric()->alignEnd(),
            ])
            ->recordUrl(fn (QrCampaign $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedQrCode)
            ->emptyStateHeading(__('qr.empty.heading'))
            ->emptyStateDescription(__('qr.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQrCampaigns::route('/'),
            'create' => CreateQrCampaign::route('/create'),
            'view' => ViewQrCampaign::route('/{record}'),
            'edit' => EditQrCampaign::route('/{record}/edit'),
        ];
    }

    /** live | scheduled | ended | off */
    public static function status(QrCampaign $campaign): string
    {
        $now = CarbonImmutable::now();

        return match (true) {
            ! $campaign->is_active => 'off',
            $campaign->starts_at !== null && $campaign->starts_at->gt($now) => 'scheduled',
            $campaign->ends_at !== null && $campaign->ends_at->lte($now) => 'ended',
            default => 'live',
        };
    }
}
