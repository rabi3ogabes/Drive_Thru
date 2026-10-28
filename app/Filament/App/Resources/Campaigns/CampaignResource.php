<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Campaigns;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Campaigns\Audience;
use App\Domain\Campaigns\CampaignText;
use App\Domain\Campaigns\Enums\CampaignChannel;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Crm\CrmSettings;
use App\Domain\Crm\Enums\Segment;
use App\Domain\Crm\UnsubscribeLink;
use App\Domain\Loyalty\Models\Reward;
use App\Filament\App\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\App\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\App\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\App\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Offers to guests who agreed to receive them: written once in both
 * languages, sent by WhatsApp, SMS or app notification, and measured by the
 * orders they bring back. A campaign is saved as a draft, checked (a test to
 * the owner's phone), then sent or scheduled from its page.
 */
class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $slug = 'campaigns';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 4;

    public const MESSAGE_LENGTH = 320;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasFeature(PlanFeature::Campaigns);
    }

    public static function getModelLabel(): string
    {
        return __('campaigns.admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('campaigns.admin.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('campaigns.admin.sections.message'))
                ->description(__('campaigns.admin.sections.message_hint'))
                ->schema([
                    TextInput::make('name')->label(__('campaigns.admin.fields.name'))->helperText(__('campaigns.admin.fields.name_hint'))->required()->maxLength(80),
                    Radio::make('channel')->label(__('campaigns.admin.fields.channel'))->options(CampaignChannel::class)
                        ->default(CampaignChannel::WhatsApp)->required()->live(),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Textarea::make('message.ar')->label(__('admin.fields.in_arabic', ['field' => __('campaigns.admin.fields.message')]))
                            ->required()->maxLength(self::MESSAGE_LENGTH)->rows(3)->autosize()->live(debounce: 600)
                            ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                        Textarea::make('message.en')->label(__('admin.fields.in_english', ['field' => __('campaigns.admin.fields.message')]))
                            ->required()->maxLength(self::MESSAGE_LENGTH)->rows(3)->autosize()->live(debounce: 600)
                            ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
                    ]),
                    Html::make(fn (Get $get): HtmlString => self::preview($get)),
                ]),
            Section::make(__('campaigns.admin.sections.audience'))
                ->description(__('campaigns.admin.sections.audience_hint'))
                ->schema([
                    CheckboxList::make('segments')->hiddenLabel()
                        ->options(Segment::class)
                        ->descriptions(fn (): array => self::segmentHints())
                        ->columns(['default' => 1, 'sm' => 2])
                        ->live(),
                    Html::make(fn (Get $get): HtmlString => self::audience((array) $get('segments'))),
                ]),
            Section::make(__('campaigns.admin.sections.reward'))
                ->description(__('campaigns.admin.sections.reward_hint'))
                ->visible(fn (): bool => Workspace::restaurant()->hasFeature(PlanFeature::Loyalty) && Reward::query()->where('is_active', true)->exists())
                ->schema([
                    Select::make('reward_id')->label(__('campaigns.admin.fields.reward'))
                        ->placeholder(__('campaigns.admin.fields.reward_none'))
                        ->options(fn (): array => Reward::query()->where('is_active', true)->orderBy('id')->get()->mapWithKeys(fn (Reward $r): array => [$r->id => $r->name])->all()),
                ]),
            Section::make(__('campaigns.admin.fields.when'))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    ToggleButtons::make('when')->hiddenLabel()
                        ->options(['now' => __('campaigns.admin.fields.now'), 'later' => __('campaigns.admin.fields.later')])
                        ->icons(['now' => 'heroicon-m-bolt', 'later' => 'heroicon-m-calendar-days'])
                        ->default('now')->inline()->live(),
                    DateTimePicker::make('scheduled_at')->label(__('campaigns.admin.fields.scheduled_at'))
                        ->seconds(false)->native(false)->minutesStep(5)
                        ->timezone(fn (): string => Workspace::restaurant()->timezone)
                        ->minDate(fn (): CarbonImmutable => CarbonImmutable::now())
                        ->required(fn (Get $get): bool => $get('when') === 'later')
                        ->visible(fn (Get $get): bool => $get('when') === 'later'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tz = fn (): string => Workspace::restaurant()->timezone;

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('campaigns.admin.fields.name'))->weight('medium')->searchable()
                    ->description(fn (Campaign $record): string => $record->channel->getLabel()),
                TextColumn::make('status')->badge(),
                TextColumn::make('segments')->label(__('campaigns.admin.columns.audience'))
                    ->state(fn (Campaign $record): string => self::audienceLabel($record))->wrap()->toggleable(),
                TextColumn::make('scheduled_at')->label(__('campaigns.admin.columns.when'))
                    ->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('—')->toggleable(),
                TextColumn::make('sent_count')->label(__('campaigns.admin.columns.sent'))->numeric()->alignEnd(),
                TextColumn::make('orders_count')->label(__('campaigns.admin.columns.orders'))->numeric()->alignEnd(),
                TextColumn::make('revenue_minor')->label(__('campaigns.admin.columns.revenue'))->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state, Workspace::restaurant()->currency)),
            ])
            ->recordUrl(fn (Campaign $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->emptyStateHeading(__('campaigns.admin.empty.heading'))
            ->emptyStateDescription(__('campaigns.admin.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }

    /**
     * Form state → model attributes. "As soon as possible" leaves the time empty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        $segments = array_values(array_filter(array_map(
            fn (mixed $value): ?string => Segment::tryFrom($value instanceof Segment ? $value->value : (string) $value)?->value,
            (array) ($data['segments'] ?? []),
        )));

        return [
            'name' => trim((string) $data['name']),
            'channel' => $data['channel'],
            'message' => ['ar' => trim((string) ($data['message']['ar'] ?? '')), 'en' => trim((string) ($data['message']['en'] ?? ''))],
            'segments' => $segments === [] ? null : $segments,
            'reward_id' => filled($data['reward_id'] ?? null) ? (int) $data['reward_id'] : null,
            'scheduled_at' => ($data['when'] ?? 'now') === 'later' && filled($data['scheduled_at'] ?? null) ? CarbonImmutable::parse($data['scheduled_at']) : null,
        ];
    }

    public static function audienceLabel(Campaign $campaign): string
    {
        $segments = $campaign->segmentList();

        return $segments === [] ? __('campaigns.admin.everyone') : implode(' · ', array_map(fn (Segment $s): string => $s->getLabel(), $segments));
    }

    /** Is the campaign for later (true) or as soon as it is sent (false)? */
    public static function isForLater(Campaign $campaign): bool
    {
        return $campaign->scheduled_at !== null && $campaign->scheduled_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    private static function segmentHints(): array
    {
        $settings = CrmSettings::for(Workspace::restaurant());

        return collect(Segment::cases())->mapWithKeys(fn (Segment $s): array => [$s->value => $s->description($settings)])->all();
    }

    private static function preview(Get $get): HtmlString
    {
        $restaurant = Workspace::restaurant();
        $channel = $get('channel');
        $channel = $channel instanceof CampaignChannel ? $channel : (CampaignChannel::tryFrom((string) $channel) ?? CampaignChannel::WhatsApp);
        $campaign = new Campaign(['channel' => $channel, 'message' => ['ar' => (string) $get('message.ar'), 'en' => (string) $get('message.en')]]);
        $campaign->setAttribute('link_code', 'preview');
        $stop = UnsubscribeLink::sampleUrl($restaurant);

        $texts = [];

        foreach (['ar', 'en'] as $locale) {
            $text = CampaignText::make($campaign, $restaurant, $locale, $stop);
            $texts[$locale] = blank($get('message.'.$locale)) ? null : ($channel === CampaignChannel::Push ? $text->offer : $text->sms());
        }

        return new HtmlString(view('filament.app.resources.campaigns.preview', [
            'texts' => $texts,
            'channel' => $channel,
            'restaurant' => $restaurant,
        ])->render());
    }

    /**
     * @param  array<mixed>  $values
     */
    private static function audience(array $values): HtmlString
    {
        $segments = array_values(array_filter(array_map(
            fn (mixed $value): ?Segment => $value instanceof Segment ? $value : Segment::tryFrom((string) $value),
            $values,
        )));
        $restaurant = Workspace::restaurant();
        $audience = app(Audience::class);

        return new HtmlString(view('filament.app.resources.campaigns.audience', [
            'count' => $audience->count($restaurant->id, $segments),
            'sample' => $audience->query($restaurant->id, $segments)->latest('last_order_at')->limit(3)->get(['id', 'name', 'phone']),
        ])->render());
    }

    /** Statuses whose page shows live results. */
    public static function isLive(Campaign $campaign): bool
    {
        return $campaign->status === CampaignStatus::Sending;
    }
}
