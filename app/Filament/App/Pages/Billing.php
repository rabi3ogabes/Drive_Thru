<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Actions\ManageServices;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Exceptions\ServiceProblem;
use App\Domain\Services\Models\RestaurantService;
use App\Domain\Services\ServicePricing;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * The owner's services (try, keep, add, cancel, with the monthly total as
 * it will be billed), the plan tier, how to pay, and every invoice as a
 * downloadable PDF.
 */
class Billing extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Restaurant;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $restaurant = Filament::getTenant();

        return $user instanceof User
            && $restaurant instanceof Restaurant
            && $user->hasRestaurantPermission($restaurant, RestaurantPermission::ManageBilling);
    }

    public static function getNavigationLabel(): string
    {
        return __('billing.owner.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing.owner.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('billing.owner.subheading');
    }

    public function restaurant(): Restaurant
    {
        /** @var Restaurant $restaurant */
        $restaurant = Filament::getTenant();

        return $restaurant->loadMissing('subscription.plan');
    }

    /**
     * The three services as the owner sees them, with today's monthly total.
     *
     * @return array{services: list<array{service: Service, row: ?RestaurantService, list_minor: int, demo_days: int, for_sale: bool}>, quote: array{lines: list<array{service: Service, unit_minor: int, amount_minor: int}>, subtotal_minor: int, discount_bp: int, discount_minor: int, total_minor: int, branches: int}, currency: string}
     */
    public function services(): array
    {
        $pricing = app(ServicePricing::class);
        $restaurant = $this->restaurant();

        return [
            'services' => array_map(fn (Service $service): array => [
                'service' => $service,
                'row' => $restaurant->service($service),
                'list_minor' => $pricing->listPriceMinor($service),
                'demo_days' => $pricing->demoDays($service),
                'for_sale' => $pricing->forSale($service),
            ], Service::cases()),
            'quote' => $pricing->restaurantQuote($restaurant, includeKeptDemos: true),
            'currency' => $pricing->currency(),
        ];
    }

    public function tryServiceAction(): Action
    {
        return Action::make('tryService')
            ->label(fn (array $arguments): string => __('services.owner.try', ['days' => app(ServicePricing::class)->demoDays(self::serviceFrom($arguments))]))
            ->icon('heroicon-m-sparkles')
            ->button()
            ->action(fn (array $arguments) => $this->runService(
                fn (ManageServices $s, Service $service, User $user) => $s->startDemo($this->restaurant(), $service, $user),
                $arguments,
                fn (Service $service): string => __('services.owner.done.demo', ['service' => $service->getLabel(), 'days' => app(ServicePricing::class)->demoDays($service)]),
            ));
    }

    public function keepServiceAction(): Action
    {
        return Action::make('keepService')
            ->label(__('services.owner.keep'))
            ->icon('heroicon-m-check')
            ->button()
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-badge')
            ->modalHeading(fn (array $arguments): string => self::serviceFrom($arguments)->getLabel())
            ->modalDescription(fn (array $arguments): string => __('services.owner.keep_confirm', [
                'service' => self::serviceFrom($arguments)->getLabel(),
                'monthly' => $this->monthlyFor(self::serviceFrom($arguments)),
                'branches' => app(ServicePricing::class)->billableBranches($this->restaurant()),
            ]))
            ->action(fn (array $arguments) => $this->runService(
                fn (ManageServices $s, Service $service, User $user) => $s->keepAfterDemo($this->restaurant(), $service, true, $user),
                $arguments,
                fn (Service $service): string => __('services.owner.done.keep', ['service' => $service->getLabel()]),
            ));
    }

    public function dontKeepServiceAction(): Action
    {
        return Action::make('dontKeepService')
            ->label(__('services.owner.dont_keep'))
            ->link()
            ->color('gray')
            ->action(fn (array $arguments) => $this->runService(
                fn (ManageServices $s, Service $service, User $user) => $s->keepAfterDemo($this->restaurant(), $service, false, $user),
                $arguments,
                fn (Service $service): string => __('services.owner.done.dont_keep', ['service' => $service->getLabel()]),
            ));
    }

    public function addServiceAction(): Action
    {
        return Action::make('addService')
            ->label(__('services.owner.add'))
            ->icon('heroicon-m-plus')
            ->button()
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-plus-circle')
            ->modalHeading(fn (array $arguments): string => self::serviceFrom($arguments)->getLabel())
            ->modalDescription(function (array $arguments): string {
                $service = self::serviceFrom($arguments);
                $restaurant = $this->restaurant();
                $pricing = app(ServicePricing::class);
                $services = app(ManageServices::class);
                $before = $pricing->restaurantQuote($restaurant);
                $prices = collect($before['lines'])->mapWithKeys(fn (array $l): array => [$l['service']->value => $l['unit_minor']])->all();
                $prices[$service->value] = $restaurant->service($service)->unit_price_minor ?? $pricing->listPriceMinor($service);
                $after = $pricing->quote($prices, $before['branches']);
                $today = $services->prorate($restaurant, $before, $after['total_minor']);

                return __('services.owner.add_confirm', [
                    'service' => $service->getLabel(),
                    'amount' => Money::format($today['amount_minor'], $pricing->currency()),
                    'monthly' => $this->monthlyFor($service),
                ]);
            })
            ->action(fn (array $arguments) => $this->runService(
                fn (ManageServices $s, Service $service, User $user) => $s->activate($this->restaurant(), $service, $user),
                $arguments,
                fn (Service $service): string => __('services.owner.done.added', ['service' => $service->getLabel()]),
            ));
    }

    public function cancelServiceAction(): Action
    {
        return Action::make('cancelService')
            ->label(__('services.owner.cancel'))
            ->link()
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => self::serviceFrom($arguments)->getLabel())
            ->modalDescription(fn (array $arguments): string => __('services.owner.cancel_confirm', [
                'service' => self::serviceFrom($arguments)->getLabel(),
                'date' => $this->restaurant()->subscription?->current_period_end?->timezone($this->restaurant()->timezone)->format('Y-m-d') ?? now()->format('Y-m-d'),
            ]))
            ->action(fn (array $arguments) => $this->runService(
                fn (ManageServices $s, Service $service, User $user) => $s->cancel($this->restaurant(), $service, $user),
                $arguments,
                fn (Service $service): string => __('services.owner.done.cancelling', [
                    'service' => $service->getLabel(),
                    'date' => $this->restaurant()->service($service)?->ends_at?->timezone($this->restaurant()->timezone)->format('Y-m-d') ?? now()->format('Y-m-d'),
                ]),
            ));
    }

    public function resumeServiceAction(): Action
    {
        return Action::make('resumeService')
            ->label(__('services.owner.resume'))
            ->button()
            ->action(fn (array $arguments) => $this->runService(
                fn (ManageServices $s, Service $service, User $user) => $s->resume($this->restaurant(), $service, $user),
                $arguments,
                fn (Service $service): string => __('services.owner.done.resumed', ['service' => $service->getLabel()]),
            ));
    }

    /**
     * @param  callable(ManageServices, Service, User): mixed  $change
     * @param  array<string, mixed>  $arguments
     * @param  callable(Service): string  $done
     */
    private function runService(callable $change, array $arguments, callable $done): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var User $user */
        $user = auth()->user();
        $service = self::serviceFrom($arguments);

        try {
            $change(app(ManageServices::class), $service, $user);
        } catch (ServiceProblem $problem) {
            Notification::make()->danger()->title($problem->getMessage())->send();

            return;
        }

        $this->restaurant()->unsetRelation('services');
        Notification::make()->success()->title($done($service))->send();
    }

    private function monthlyFor(Service $service): string
    {
        $pricing = app(ServicePricing::class);
        $restaurant = $this->restaurant();
        $unit = $restaurant->service($service)->unit_price_minor ?? $pricing->listPriceMinor($service);

        return Money::format($unit * $pricing->billableBranches($restaurant), $pricing->currency());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function serviceFrom(array $arguments): Service
    {
        $service = Service::tryFrom((string) ($arguments['service'] ?? ''));
        abort_if($service === null, 404);

        return $service;
    }

    public function bankDetails(): ?string
    {
        return app(PlatformSettings::class)->string('billing.bank_details');
    }

    public function billingEmail(): ?string
    {
        return app(PlatformSettings::class)->string('billing.email');
    }

    public function table(Table $table): Table
    {
        return InvoicesTable::configure($table, showClient: false)
            ->query(Invoice::query()->where('restaurant_id', $this->restaurant()->id))
            ->recordActions([
                Action::make('downloadPdf')
                    ->label(__('billing.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (Invoice $record): string => route('invoices.pdf', $record), shouldOpenInNewTab: true),
            ]);
    }
}
