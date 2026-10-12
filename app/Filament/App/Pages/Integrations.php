<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Integrations\Actions\SaveIntegration;
use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Pos\GenericPosDriver;
use App\Domain\Integrations\Webhooks\SafeUrl;
use App\Domain\Payments\GatewayResolver;
use App\Domain\Payments\Sadad\SadadGateway;
use App\Domain\Printing\PrintNodePrinter;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Lang;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * The restaurant's own provider accounts: online payments (SADAD) and
 * kitchen printing (PrintNode). Keys are encrypted and write-only; leaving
 * a key field empty keeps the saved one.
 *
 * @property-read Schema $form
 */
class Integrations extends Page
{
    protected static ?string $slug = 'integrations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Restaurant;

    protected static ?int $navigationSort = 3;

    private const SADAD_SECRETS = ['secret_key_test', 'secret_key_live', 'api_key_test', 'api_key_live'];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Workspace::can(RestaurantPermission::ManageIntegrations);
    }

    public static function getNavigationLabel(): string
    {
        return __('integrations.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('integrations.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('integrations.subheading');
    }

    public function mount(): void
    {
        $payments = $this->integration(IntegrationKind::Payments);
        $printing = $this->integration(IntegrationKind::Printing);
        $pos = $this->integration(IntegrationKind::Pos);

        $this->form->fill([
            'payments' => [
                'provider' => $payments->provider ?? '',
                'enabled' => $payments->is_enabled ?? false,
                'mode' => $payments->mode ?? Integration::TEST,
                'merchant_id' => $payments?->credential('merchant_id'),
                'website' => $payments?->credential('website'),
                'api_sadad_id' => $payments?->credential('api_sadad_id'),
                'api_domain' => $payments?->credential('api_domain'),
                'partial_refunds' => (bool) $payments?->option('partial_refunds', false),
            ],
            'printing' => [
                'enabled' => $printing->is_enabled ?? false,
            ],
            'pos' => [
                'enabled' => $pos->is_enabled ?? false,
                'order_url' => $pos?->credential('order_url'),
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $saved = fn (IntegrationKind $kind, string $key): bool => $this->integration($kind)?->credential($key) !== null;
        $secret = fn (string $name, string $label, IntegrationKind $kind, string $key) => TextInput::make($name)
            ->label($label)
            ->password()
            ->autocomplete('new-password')
            ->maxLength(200)
            ->placeholder(fn (): string => $saved($kind, $key) ? Lang::text('integrations.saved_secret') : '')
            ->helperText(fn (): ?string => $saved($kind, $key) ? Lang::text('integrations.saved_secret_hint') : null);

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->persistTabInQueryString()->tabs([
                    Tab::make(__('integrations.tabs.payments'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Section::make(__('integrations.payments.provider'))
                                ->description(__('integrations.payments.provider_hint'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    Select::make('payments.provider')
                                        ->label(__('integrations.payments.provider'))
                                        ->options(['' => __('integrations.payments.none'), SadadGateway::NAME => 'SADAD (Qatar)'])
                                        ->native(false)
                                        ->live()
                                        ->helperText(fn (): ?string => Workspace::restaurant()->currency !== 'QAR' ? Lang::text('integrations.payments.qar_only') : null),
                                    Radio::make('payments.mode')
                                        ->label(__('integrations.mode'))
                                        ->options([Integration::TEST => __('integrations.modes.test'), Integration::LIVE => __('integrations.modes.live')])
                                        ->descriptions([Integration::TEST => __('integrations.modes.test_hint'), Integration::LIVE => __('integrations.modes.live_hint')])
                                        ->inline()
                                        ->visible(fn (Get $get): bool => $get('payments.provider') === SadadGateway::NAME),
                                    Toggle::make('payments.enabled')
                                        ->label(__('integrations.payments.enabled'))
                                        ->helperText(__('integrations.payments.enabled_hint'))
                                        ->visible(fn (Get $get): bool => $get('payments.provider') === SadadGateway::NAME)
                                        ->columnSpanFull(),
                                ]),
                            Section::make(__('integrations.sadad.checkout'))
                                ->description(__('integrations.sadad.checkout_hint'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->visible(fn (Get $get): bool => $get('payments.provider') === SadadGateway::NAME)
                                ->schema([
                                    TextInput::make('payments.merchant_id')->label(__('integrations.sadad.merchant_id'))->maxLength(30)->requiredIf('payments.enabled', true),
                                    TextInput::make('payments.website')->label(__('integrations.sadad.website'))->helperText(__('integrations.sadad.website_hint'))->maxLength(120)->requiredIf('payments.enabled', true),
                                    $secret('payments.secret_key_test', __('integrations.sadad.secret_key_test'), IntegrationKind::Payments, 'secret_key_test'),
                                    $secret('payments.secret_key_live', __('integrations.sadad.secret_key_live'), IntegrationKind::Payments, 'secret_key_live'),
                                ]),
                            Section::make(__('integrations.sadad.api'))
                                ->description(__('integrations.sadad.api_hint'))
                                ->columns(['default' => 1, 'md' => 2])
                                ->visible(fn (Get $get): bool => $get('payments.provider') === SadadGateway::NAME)
                                ->schema([
                                    TextInput::make('payments.api_sadad_id')->label(__('integrations.sadad.sadad_id'))->numeric()->maxLength(20),
                                    TextInput::make('payments.api_domain')->label(__('integrations.sadad.api_domain'))->maxLength(120),
                                    $secret('payments.api_key_test', __('integrations.sadad.api_key_test'), IntegrationKind::Payments, 'api_key_test'),
                                    $secret('payments.api_key_live', __('integrations.sadad.api_key_live'), IntegrationKind::Payments, 'api_key_live'),
                                    Toggle::make('payments.partial_refunds')->label(__('integrations.sadad.partial_refunds'))->helperText(__('integrations.sadad.partial_refunds_hint'))->columnSpanFull(),
                                ]),
                            Section::make(__('integrations.sadad.webhook'))
                                ->description(__('integrations.sadad.webhook_hint'))
                                ->visible(fn (Get $get): bool => $get('payments.provider') === SadadGateway::NAME)
                                ->schema([
                                    TextEntry::make('sadad_webhook')->hiddenLabel()->state(route('payments.sadad.webhook'))->copyable()->fontFamily('mono'),
                                ])
                                ->headerActions([
                                    Action::make('testSadad')
                                        ->label(__('integrations.test_connection'))
                                        ->icon('heroicon-o-signal')
                                        ->action(fn () => $this->testSadad()),
                                ]),
                        ]),
                    Tab::make(__('integrations.tabs.printing'))
                        ->icon('heroicon-o-printer')
                        ->schema([
                            Section::make('PrintNode')
                                ->description(__('integrations.printing.hint'))
                                ->schema([
                                    Toggle::make('printing.enabled')->label(__('integrations.printing.enabled')),
                                    $secret('printing.api_key', __('integrations.printing.api_key'), IntegrationKind::Printing, 'api_key'),
                                    TextEntry::make('printing_branches')->hiddenLabel()->state(__('integrations.printing.branches_hint')),
                                ])
                                ->headerActions([
                                    Action::make('testPrinting')
                                        ->label(__('integrations.test_connection'))
                                        ->icon('heroicon-o-signal')
                                        ->action(fn () => $this->testPrinting()),
                                ]),
                        ]),
                    Tab::make(__('integrations.tabs.pos'))
                        ->icon('heroicon-o-computer-desktop')
                        ->schema([
                            Section::make(__('integrations.pos.title'))
                                ->description(__('integrations.pos.hint'))
                                ->schema([
                                    Toggle::make('pos.enabled')->label(__('integrations.pos.enabled'))->helperText(__('integrations.pos.enabled_hint')),
                                    TextInput::make('pos.order_url')
                                        ->label(__('integrations.pos.order_url'))
                                        ->helperText(__('integrations.pos.order_url_hint'))
                                        ->url()
                                        ->maxLength(500)
                                        ->requiredIf('pos.enabled', true)
                                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                            if (filled($value) && ! app(SafeUrl::class)->allows((string) $value)) {
                                                $fail(Lang::text('integrations.webhooks.errors.url'));
                                            }
                                        })
                                        ->extraInputAttributes(['dir' => 'ltr']),
                                    $secret('pos.api_key', __('integrations.pos.api_key'), IntegrationKind::Pos, 'api_key'),
                                    TextEntry::make('pos_other')->hiddenLabel()->state(__('integrations.pos.other_ways')),
                                ])
                                ->headerActions([
                                    Action::make('testPos')
                                        ->label(__('integrations.test_connection'))
                                        ->icon('heroicon-o-signal')
                                        ->action(fn () => $this->testPos()),
                                ]),
                        ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('integrations')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('settings.save'))->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $state = $this->form->getState();
        $restaurant = Workspace::restaurant();
        $payments = (array) ($state['payments'] ?? []);
        $printing = (array) ($state['printing'] ?? []);
        $pos = (array) ($state['pos'] ?? []);
        $save = app(SaveIntegration::class);

        if (($payments['provider'] ?? '') === SadadGateway::NAME) {
            $save->handle(
                $restaurant,
                IntegrationKind::Payments,
                SadadGateway::NAME,
                (string) ($payments['mode'] ?? Integration::TEST),
                (bool) ($payments['enabled'] ?? false),
                [
                    'merchant_id' => $payments['merchant_id'] ?? null,
                    'website' => $payments['website'] ?? null,
                    'api_sadad_id' => $payments['api_sadad_id'] ?? null,
                    'api_domain' => $payments['api_domain'] ?? null,
                    ...array_intersect_key($payments, array_flip(self::SADAD_SECRETS)),
                ],
                ['partial_refunds' => (bool) ($payments['partial_refunds'] ?? false)],
                Workspace::user(),
            );
        } elseif ($existing = $this->integration(IntegrationKind::Payments)) {
            $existing->forceFill(['is_enabled' => false])->save();
        }

        $save->handle($restaurant, IntegrationKind::Printing, PrintNodePrinter::NAME, Integration::LIVE, (bool) ($printing['enabled'] ?? false), ['api_key' => $printing['api_key'] ?? null], [], Workspace::user());
        $save->handle($restaurant, IntegrationKind::Pos, GenericPosDriver::NAME, Integration::LIVE, (bool) ($pos['enabled'] ?? false), ['order_url' => $pos['order_url'] ?? null, 'api_key' => $pos['api_key'] ?? null], [], Workspace::user());

        app(GatewayResolver::class)->forget();
        $this->mount();
        Notification::make()->success()->title(__('integrations.saved'))->send();
    }

    public function testSadad(): void
    {
        $integration = $this->integration(IntegrationKind::Payments);
        $ok = $integration !== null && $integration->provider === SadadGateway::NAME
            && app()->make(SadadGateway::class, ['integration' => $integration])->verifyConnection();

        $this->recordCheck($integration, $ok);
    }

    public function testPrinting(): void
    {
        $integration = $this->integration(IntegrationKind::Printing);
        $ok = $integration !== null && app()->make(PrintNodePrinter::class, ['integration' => $integration])->verifyConnection();

        $this->recordCheck($integration, $ok);
    }

    public function testPos(): void
    {
        $integration = $this->integration(IntegrationKind::Pos);
        $ok = $integration !== null && app()->make(GenericPosDriver::class, ['integration' => $integration])->verifyConnection();

        $this->recordCheck($integration, $ok);
    }

    private function recordCheck(?Integration $integration, bool $ok): void
    {
        $integration?->forceFill(['verified_at' => $ok ? now() : null, 'last_error' => $ok ? null : 'connection_failed'])->save();

        $ok
            ? Notification::make()->success()->title(__('integrations.connection_ok'))->send()
            : Notification::make()->danger()->title(__('integrations.connection_failed'))->body(__('integrations.connection_failed_hint'))->send();
    }

    private function integration(IntegrationKind $kind): ?Integration
    {
        return Integration::withoutTenancy()->where('restaurant_id', Workspace::restaurant()->id)->where('kind', $kind->value)->first();
    }
}
