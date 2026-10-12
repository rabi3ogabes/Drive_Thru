<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Platform\PlatformSettings;
use App\Filament\Admin\AdminNavigationGroup;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
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
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Platform-wide settings: billing defaults and invoice identity, operations
 * switches, and the banner shown in every restaurant workspace.
 *
 * @property-read Schema $form
 */
class PlatformSettingsPage extends Page
{
    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 9;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformPermission(PlatformPermission::ManageSettings);
    }

    public static function getNavigationLabel(): string
    {
        return __('platform.settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform.settings.subheading');
    }

    public function mount(): void
    {
        $settings = app(PlatformSettings::class);
        $all = $settings->all();

        $this->form->fill(Arr::undot(array_merge($all, [
            'billing.tax_rate_percent' => $settings->int('billing.tax_rate_bp') / 100,
        ])));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->persistTabInQueryString()->tabs([
                    Tab::make(__('platform.settings.tabs.billing'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Section::make(__('platform.settings.sections.billing_rules'))
                                ->columns(['default' => 1, 'md' => 3])
                                ->schema([
                                    TextInput::make('billing.trial_days')->label(__('platform.settings.fields.trial_days'))->numeric()->minValue(0)->maxValue(90)->required()->suffix(__('plans.fields.days')),
                                    TextInput::make('billing.invoice_due_days')->label(__('platform.settings.fields.invoice_due_days'))->numeric()->minValue(0)->maxValue(60)->required()->suffix(__('plans.fields.days')),
                                    TextInput::make('billing.grace_days')->label(__('platform.settings.fields.grace_days'))->helperText(__('platform.settings.fields.grace_days_hint'))->numeric()->minValue(0)->maxValue(60)->required()->suffix(__('plans.fields.days')),
                                    TextInput::make('billing.tax_rate_percent')->label(__('platform.settings.fields.tax_rate'))->numeric()->minValue(0)->maxValue(50)->step(0.01)->required()->suffix('%'),
                                    Select::make('billing.currency')
                                        ->label(__('platform.settings.fields.currency'))
                                        ->options(array_combine(Money::currencies(), Money::currencies()))
                                        ->required()
                                        ->native(false),
                                ]),
                            Section::make(__('platform.settings.sections.invoice_identity'))
                                ->description(__('platform.settings.sections.invoice_identity_hint'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    TextInput::make('billing.legal_name')->label(__('platform.settings.fields.legal_name'))->required()->maxLength(160),
                                    TextInput::make('billing.tax_number')->label(__('platform.settings.fields.tax_number'))->maxLength(40),
                                    TextInput::make('billing.email')->label(__('platform.settings.fields.billing_email'))->email()->maxLength(190),
                                    TextInput::make('billing.address')->label(__('platform.settings.fields.address'))->maxLength(300),
                                    Textarea::make('billing.bank_details')
                                        ->label(__('platform.settings.fields.bank_details'))
                                        ->helperText(__('platform.settings.fields.bank_details_hint'))
                                        ->rows(3)
                                        ->autosize()
                                        ->maxLength(1000)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tab::make(__('platform.settings.tabs.operations'))
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([
                            Section::make()->schema([
                                Toggle::make('orders.admin_override')
                                    ->label(__('platform.settings.fields.admin_override'))
                                    ->helperText(__('platform.settings.fields.admin_override_hint')),
                                CheckboxList::make('otp.channels')
                                    ->label(__('platform.settings.fields.otp_channels'))
                                    ->helperText(__('platform.settings.fields.otp_channels_hint'))
                                    ->options(['whatsapp' => 'WhatsApp', 'sms' => 'SMS'])
                                    ->required()
                                    ->columns(2),
                            ]),
                        ]),
                    Tab::make(__('platform.settings.tabs.banner'))
                        ->icon('heroicon-o-megaphone')
                        ->schema([
                            Section::make()
                                ->description(__('platform.settings.sections.banner_hint'))
                                ->schema([
                                    Toggle::make('banner.enabled')->label(__('platform.settings.fields.banner_enabled'))->live(),
                                    ToggleButtons::make('banner.level')
                                        ->label(__('platform.announcements.fields.level'))
                                        ->options([
                                            'info' => __('platform.announcement_level.info'),
                                            'warning' => __('platform.announcement_level.warning'),
                                            'critical' => __('platform.announcement_level.critical'),
                                        ])
                                        ->colors(['info' => 'info', 'warning' => 'warning', 'critical' => 'danger'])
                                        ->inline(),
                                    Grid::make(['default' => 1, 'md' => 2])->schema([
                                        Textarea::make('banner.message.en')
                                            ->label(__('admin.fields.in_english', ['field' => __('platform.settings.fields.banner_message')]))
                                            ->required(fn (Get $get): bool => (bool) $get('banner.enabled'))
                                            ->maxLength(300)
                                            ->extraInputAttributes(['dir' => 'ltr']),
                                        Textarea::make('banner.message.ar')
                                            ->label(__('admin.fields.in_arabic', ['field' => __('platform.settings.fields.banner_message')]))
                                            ->required(fn (Get $get): bool => (bool) $get('banner.enabled'))
                                            ->maxLength(300)
                                            ->extraInputAttributes(['dir' => 'rtl']),
                                    ]),
                                ]),
                        ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('platform-settings')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('platform.settings.save'))
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
        $values = $this->flatten($state);
        $values['billing.tax_rate_bp'] = (int) round(((float) ($values['billing.tax_rate_percent'] ?? 0)) * 100);
        unset($values['billing.tax_rate_percent']);
        $values['banner.message'] = [
            'en' => $state['banner']['message']['en'] ?? null,
            'ar' => $state['banner']['message']['ar'] ?? null,
        ];
        $values['otp.channels'] = array_values((array) ($state['otp']['channels'] ?? ['whatsapp']));

        app(PlatformSettings::class)->set($values);

        activity()->causedBy(auth()->user())->event('settings.updated')->withProperties(['keys' => array_keys($values)])->log('Platform settings updated');

        Notification::make()->success()->title(__('platform.settings.saved'))->send();
    }

    /**
     * {"billing": {"trial_days": 14}} ⇄ {"billing.trial_days": 14}, keeping
     * the per-language banner message and channel list as values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function flatten(array $data): array
    {
        if (array_key_exists('billing.trial_days', $data)) {
            return $data; // already flat (from PlatformSettings::all())
        }

        $flat = [];

        foreach ($data as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                $flat["{$group}.{$key}"] = $value;
            }
        }

        return $flat;
    }
}
