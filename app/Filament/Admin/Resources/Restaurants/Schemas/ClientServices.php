<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Schemas;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Services\Actions\ManageServices;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Exceptions\ServiceProblem;
use App\Domain\Services\ServicePricing;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * The client page's "Services" card: each of the three services with its
 * status, price and demo days, and what the platform can do about it
 * (demo, activate, extend, price, end). Every change asks for a reason
 * where money or access changes, and lands in the audit log.
 */
final class ClientServices
{
    public static function section(): Section
    {
        return Section::make(__('services.admin.title'))
            ->icon('heroicon-o-squares-2x2')
            ->columnSpanFull()
            ->schema(array_map(fn (Service $service): Grid => self::row($service), Service::cases()));
    }

    private static function row(Service $service): Grid
    {
        $row = fn (Restaurant $record) => $record->service($service);

        return Grid::make(['default' => 1, 'sm' => 3])->schema([
            TextEntry::make('service_'.$service->value)
                ->hiddenLabel()
                ->state($service->getLabel())
                ->icon($service->getIcon())
                ->weight('semibold')
                ->helperText(function (Restaurant $record) use ($row, $service): string {
                    $current = $row($record);
                    $currency = app(ServicePricing::class)->currency();
                    $price = Money::format($current->unit_price_minor ?? app(ServicePricing::class)->listPriceMinor($service), $currency).' '.__('services.per_branch_month');

                    return match (true) {
                        $current?->status === ServiceStatus::Demo => trans_choice('services.demo_left', (int) $current->demoDaysLeft(), ['count' => $current->demoDaysLeft()]).' · '.$price,
                        $current?->status === ServiceStatus::Cancelling => __('services.ends_on', ['date' => $current->ends_at?->format('Y-m-d')]),
                        default => $price,
                    };
                }),
            TextEntry::make('service_status_'.$service->value)
                ->hiddenLabel()
                ->badge()
                ->state(fn (Restaurant $record): string => $row($record)?->status->getLabel() ?? __('services.admin.not_added'))
                ->color(fn (Restaurant $record): string => $row($record)?->status->getColor() ?? 'gray'),
            Actions::make([
                ActionGroup::make([
                    self::demo($service),
                    self::activate($service),
                    self::extend($service),
                    self::price($service),
                    self::end($service),
                ])->label(__('services.admin.title'))->icon('heroicon-m-ellipsis-horizontal')->button()->color('gray'),
            ])->key('service_actions_'.$service->value)->alignEnd()->visible(fn (): bool => self::allowed()),
        ]);
    }

    private static function demo(Service $service): Action
    {
        return Action::make('serviceDemo_'.$service->value)
            ->label(fn (Restaurant $record): string => $record->service($service)?->demo_used_at !== null ? __('services.admin.another_demo') : __('services.admin.add_demo'))
            ->icon('heroicon-o-sparkles')
            ->visible(fn (Restaurant $record): bool => ! $record->hasService($service))
            ->schema(fn (Restaurant $record): array => [
                TextInput::make('days')->label(__('services.admin.days'))->numeric()->integer()->minValue(1)->maxValue(365)
                    ->default(app(ServicePricing::class)->demoDays($service))->required(),
                self::reason()->required($record->service($service)?->demo_used_at !== null),
            ])
            ->action(fn (Restaurant $record, array $data) => self::run(fn (ManageServices $s, User $u) => $s->startDemo(
                $record, $service, $u, (int) $data['days'], grantAnother: $record->service($service)?->demo_used_at !== null, reason: $data['reason'] ?? null,
            )));
    }

    private static function activate(Service $service): Action
    {
        return Action::make('serviceActivate_'.$service->value)
            ->label(__('services.admin.activate'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Restaurant $record): bool => ! ($record->service($service)?->status->isBilled() ?? false))
            ->schema(fn (Restaurant $record): array => [
                self::priceInput()->default(Money::toMajor($record->service($service)->unit_price_minor ?? app(ServicePricing::class)->listPriceMinor($service), app(ServicePricing::class)->currency())),
                self::reason(),
            ])
            ->action(fn (Restaurant $record, array $data) => self::run(fn (ManageServices $s, User $u) => $s->activate(
                $record, $service, $u, Money::toMinor((string) $data['price'], app(ServicePricing::class)->currency()), $data['reason'] ?? null,
            )));
    }

    private static function extend(Service $service): Action
    {
        return Action::make('serviceExtend_'.$service->value)
            ->label(__('services.admin.extend'))
            ->icon('heroicon-o-calendar-days')
            ->visible(fn (Restaurant $record): bool => $record->service($service)?->status === ServiceStatus::Demo)
            ->schema([
                TextInput::make('days')->label(__('services.admin.days'))->numeric()->integer()->minValue(1)->maxValue(365)->default(7)->required(),
                self::reason()->required(),
            ])
            ->action(fn (Restaurant $record, array $data) => self::run(fn (ManageServices $s, User $u) => $s->extendDemo($record, $service, (int) $data['days'], $u, $data['reason'])));
    }

    private static function price(Service $service): Action
    {
        return Action::make('servicePrice_'.$service->value)
            ->label(__('services.admin.override'))
            ->icon('heroicon-o-banknotes')
            ->visible(fn (Restaurant $record): bool => $record->hasService($service))
            ->schema(fn (Restaurant $record): array => [
                self::priceInput()->default(Money::toMajor((int) $record->service($service)?->unit_price_minor, app(ServicePricing::class)->currency())),
                self::reason()->required(),
            ])
            ->action(fn (Restaurant $record, array $data) => self::run(fn (ManageServices $s, User $u) => $s->setPrice(
                $record, $service, Money::toMinor((string) $data['price'], app(ServicePricing::class)->currency()), $u, $data['reason'],
            )));
    }

    private static function end(Service $service): Action
    {
        return Action::make('serviceEnd_'.$service->value)
            ->label(__('services.admin.end'))
            ->icon('heroicon-o-power')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('services.admin.end_confirm', ['service' => $service->getLabel()]))
            ->visible(fn (Restaurant $record): bool => $record->hasService($service))
            ->schema([self::reason()->required()])
            ->action(fn (Restaurant $record, array $data) => self::run(fn (ManageServices $s, User $u) => $s->end($record, $service, $u, $data['reason'])));
    }

    private static function priceInput(): TextInput
    {
        return TextInput::make('price')
            ->label(__('services.admin.unit_price'))
            ->numeric()->minValue(0)->step(0.01)
            ->suffix(fn (): string => app(ServicePricing::class)->currency())
            ->required();
    }

    private static function reason(): Textarea
    {
        return Textarea::make('reason')->label(__('services.admin.reason'))->helperText(__('services.admin.reason_hint'))->rows(2)->maxLength(300);
    }

    private static function allowed(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformPermission(PlatformPermission::ManageBilling);
    }

    /**
     * @param  callable(ManageServices, User): mixed  $change
     */
    private static function run(callable $change): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && self::allowed(), 403);

        try {
            $change(app(ManageServices::class), $user);
        } catch (ServiceProblem $problem) {
            Notification::make()->danger()->title($problem->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__('services.admin.done'))->send();
    }
}
