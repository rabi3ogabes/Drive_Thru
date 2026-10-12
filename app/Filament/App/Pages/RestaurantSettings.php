<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Enums\MenuLinkMode;
use App\Domain\Branches\Models\Branch;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use App\Support\Locales;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * The owner's restaurant settings: brand, money and language, and the rules
 * guests order under. Changes are recorded in the audit log.
 *
 * @property-read Schema $form
 */
class RestaurantSettings extends Page
{
    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Restaurant;

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Workspace::can(RestaurantPermission::ManageSettings);
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('settings.subheading');
    }

    public function mount(): void
    {
        $restaurant = Workspace::restaurant();

        $this->form->fill([
            'name' => $restaurant->getTranslations('name'),
            'logo_path' => $restaurant->logo_path,
            'cover_path' => $restaurant->cover_path,
            'accent_color' => $restaurant->accent_color,
            'default_locale' => $restaurant->default_locale,
            'timezone' => $restaurant->timezone,
            'currency' => $restaurant->currency,
            'tax_rate_percent' => $restaurant->tax_rate_bp / 100,
            'service_fee_percent' => $restaurant->service_fee_bp / 100,
            'menu_link_mode' => $restaurant->menu_link_mode,
            'order_override_enabled' => $restaurant->order_override_enabled,
            'cancellation_window_minutes' => $restaurant->cancellation_window_minutes,
            'quiet_hours_start' => $restaurant->quiet_hours_start,
            'quiet_hours_end' => $restaurant->quiet_hours_end,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $restaurant = Workspace::restaurant();
        $directory = 'restaurants/'.$restaurant->id.'/brand';

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->persistTabInQueryString()->tabs([
                    Tab::make(__('settings.tabs.brand'))
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Section::make(__('settings.sections.identity'))
                                ->description(__('settings.sections.identity_hint', ['link' => url($restaurant->slug)]))
                                ->schema([
                                    Bilingual::text('name', __('settings.fields.name'), maxLength: 80),
                                ]),
                            Section::make(__('settings.sections.look'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    FileUpload::make('logo_path')
                                        ->label(__('settings.fields.logo'))
                                        ->helperText(__('settings.fields.logo_hint'))
                                        ->image()
                                        ->imageEditor()
                                        ->imageCropAspectRatio('1:1')
                                        ->disk('public')
                                        ->directory($directory)
                                        ->visibility('public')
                                        ->maxSize(3072)
                                        ->hintAction(
                                            Action::make('applyLogo')
                                                ->label(__('settings.actions.apply_logo'))
                                                ->icon('heroicon-m-square-2-stack')
                                                ->requiresConfirmation()
                                                ->modalDescription(__('settings.apply_logo_description'))
                                                ->action(function (): void {
                                                    $count = Branch::query()->where('use_restaurant_logo', false)->update(['use_restaurant_logo' => true]);
                                                    activity()->performedOn(Workspace::restaurant())->causedBy(Workspace::user())->event('branding.logo_applied')->withProperties(['branches' => $count])->log('branding.logo_applied');
                                                    Notification::make()->success()->title(__('settings.notifications.logo_applied'))->send();
                                                }),
                                        ),
                                    FileUpload::make('cover_path')
                                        ->label(__('settings.fields.cover'))
                                        ->helperText(__('settings.fields.cover_hint'))
                                        ->image()
                                        ->imageEditor()
                                        ->imageCropAspectRatio('16:9')
                                        ->disk('public')
                                        ->directory($directory)
                                        ->visibility('public')
                                        ->maxSize(5120),
                                    ColorPicker::make('accent_color')
                                        ->label(__('settings.fields.accent_color'))
                                        ->helperText(__('settings.fields.accent_color_hint'))
                                        ->hex()
                                        ->regex('/^#[0-9a-fA-F]{6}$/'),
                                ]),
                        ]),
                    Tab::make(__('settings.tabs.region'))
                        ->icon('heroicon-o-globe-alt')
                        ->schema([
                            Section::make(__('settings.sections.region'))
                                ->columns(['default' => 1, 'md' => 3])
                                ->schema([
                                    Select::make('default_locale')
                                        ->label(__('settings.fields.default_locale'))
                                        ->helperText(__('settings.fields.default_locale_hint'))
                                        ->options(collect(Locales::codes())->mapWithKeys(fn (string $code): array => [$code => Locales::native($code)])->all())
                                        ->required()
                                        ->native(false),
                                    Select::make('timezone')
                                        ->label(__('settings.fields.timezone'))
                                        ->options(collect(timezone_identifiers_list())->mapWithKeys(fn (string $tz): array => [$tz => $tz])->all())
                                        ->searchable()
                                        ->required(),
                                    Select::make('currency')
                                        ->label(__('settings.fields.currency'))
                                        ->helperText(__('settings.fields.currency_hint'))
                                        ->options(array_combine(Money::currencies(), Money::currencies()))
                                        ->required()
                                        ->native(false),
                                ]),
                        ]),
                    Tab::make(__('settings.tabs.ordering'))
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([
                            Section::make(__('settings.sections.links'))
                                ->description(__('settings.sections.links_hint'))
                                ->schema([
                                    Radio::make('menu_link_mode')
                                        ->hiddenLabel()
                                        ->options(MenuLinkMode::class)
                                        ->required(),
                                ]),
                            Section::make(__('settings.sections.charges'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    TextInput::make('tax_rate_percent')
                                        ->label(__('settings.fields.tax_rate'))
                                        ->helperText(__('settings.fields.tax_rate_hint'))
                                        ->numeric()->minValue(0)->maxValue(50)->step(0.01)->required()->suffix('%'),
                                    TextInput::make('service_fee_percent')
                                        ->label(__('settings.fields.service_fee'))
                                        ->helperText(__('settings.fields.service_fee_hint'))
                                        ->numeric()->minValue(0)->maxValue(30)->step(0.01)->required()->suffix('%'),
                                ]),
                            Section::make(__('settings.sections.rules'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    TextInput::make('cancellation_window_minutes')
                                        ->label(__('settings.fields.cancellation_window'))
                                        ->helperText(__('settings.fields.cancellation_window_hint'))
                                        ->numeric()->integer()->minValue(0)->maxValue(30)->required()->suffix(__('branches.units.minutes')),
                                    Toggle::make('order_override_enabled')
                                        ->label(__('settings.fields.order_override'))
                                        ->helperText(__('settings.fields.order_override_hint'))
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tab::make(__('settings.tabs.alerts'))
                        ->icon('heroicon-o-bell-alert')
                        ->schema([
                            Section::make(__('settings.sections.quiet_hours'))
                                ->description(__('settings.sections.quiet_hours_hint'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    // Wall-clock times in the restaurant's own time zone: no display-time shift.
                                    TimePicker::make('quiet_hours_start')
                                        ->label(__('settings.fields.quiet_hours_start'))
                                        ->seconds(false)
                                        ->timezone('UTC')
                                        ->requiredWith('quiet_hours_end'),
                                    TimePicker::make('quiet_hours_end')
                                        ->label(__('settings.fields.quiet_hours_end'))
                                        ->seconds(false)
                                        ->timezone('UTC')
                                        ->requiredWith('quiet_hours_start')
                                        ->different('quiet_hours_start'),
                                ]),
                        ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('restaurant-settings')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('settings.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $state = $this->form->getState();
        $restaurant = Workspace::restaurant();

        $restaurant->fill([
            'name' => array_filter((array) $state['name'], 'filled'),
            'logo_path' => $state['logo_path'] ?? null,
            'cover_path' => $state['cover_path'] ?? null,
            'accent_color' => $state['accent_color'] ?? null,
            'default_locale' => $state['default_locale'],
            'timezone' => $state['timezone'],
            'currency' => $state['currency'],
            'tax_rate_bp' => (int) round(((float) $state['tax_rate_percent']) * 100),
            'service_fee_bp' => (int) round(((float) $state['service_fee_percent']) * 100),
            'menu_link_mode' => $state['menu_link_mode'],
            'order_override_enabled' => (bool) ($state['order_override_enabled'] ?? false),
            'cancellation_window_minutes' => (int) $state['cancellation_window_minutes'],
            'quiet_hours_start' => $this->wallClock($state['quiet_hours_start'] ?? null),
            'quiet_hours_end' => $this->wallClock($state['quiet_hours_end'] ?? null),
        ])->save();

        Notification::make()->success()->title(__('settings.saved'))->send();
    }

    /** "23:00:00" or "23:00" → "23:00"; empty → null. */
    private function wallClock(mixed $time): ?string
    {
        return is_string($time) && preg_match('/^\d{2}:\d{2}/', $time) === 1 ? substr($time, 0, 5) : null;
    }
}
