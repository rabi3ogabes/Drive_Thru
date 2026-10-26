<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Pages;

use App\Domain\Billing\Models\Plan;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Models\ServicePrice;
use App\Domain\Services\ServicePricing;
use App\Filament\Admin\Resources\Plans\PlanResource;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->servicePrices(),
            CreateAction::make(),
        ];
    }

    /** What each service costs per branch per month, its free demo, and the bundle discounts. */
    private function servicePrices(): Action
    {
        $pricing = app(ServicePricing::class);
        $currency = $pricing->currency();

        return Action::make('servicePrices')
            ->label(__('services.admin.prices'))
            ->icon('heroicon-o-squares-2x2')
            ->color('gray')
            ->modalHeading(__('services.admin.prices'))
            ->modalDescription(__('services.admin.prices_hint'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->visible(fn (): bool => Gate::allows('create', Plan::class))
            ->fillForm(function () use ($pricing, $currency): array {
                $data = [
                    'bundle_two' => $pricing->bundleDiscountBp(2) / 100,
                    'bundle_three' => $pricing->bundleDiscountBp(3) / 100,
                ];

                foreach (Service::cases() as $service) {
                    $price = $pricing->price($service);
                    $data[$service->value] = [
                        'price' => Money::toMajor($price->price_minor ?? 0, $currency),
                        'demo_days' => $price->demo_days ?? 14,
                        'is_active' => $price->is_active ?? true,
                    ];
                }

                return $data;
            })
            ->schema([
                ...array_map(fn (Service $service): Section => Section::make($service->getLabel())
                    ->icon($service->getIcon())
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make("{$service->value}.price")->label(__('services.admin.price'))->numeric()->minValue(0)->step(0.01)->suffix($currency)->required(),
                            TextInput::make("{$service->value}.demo_days")->label(__('services.admin.demo_days'))->numeric()->integer()->minValue(1)->maxValue(90)->suffix(__('services.admin.days'))->required(),
                            Toggle::make("{$service->value}.is_active")->label(__('services.admin.active'))->inline(false),
                        ]),
                    ]), Service::cases()),
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('bundle_two')->label(__('services.admin.bundle_two'))->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                    TextInput::make('bundle_three')->label(__('services.admin.bundle_three'))->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                ]),
            ])
            ->action(function (array $data) use ($currency): void {
                DB::transaction(function () use ($data, $currency): void {
                    foreach (Service::cases() as $sort => $service) {
                        ServicePrice::query()->updateOrCreate(['service' => $service->value], [
                            'price_minor' => Money::toMinor((string) $data[$service->value]['price'], $currency),
                            'currency' => $currency,
                            'demo_days' => (int) $data[$service->value]['demo_days'],
                            'is_active' => (bool) $data[$service->value]['is_active'],
                            'sort' => $sort + 1,
                        ]);
                    }

                    app(PlatformSettings::class)->set([
                        'billing.bundle_two_bp' => (int) round(((float) $data['bundle_two']) * 100),
                        'billing.bundle_three_bp' => (int) round(((float) $data['bundle_three']) * 100),
                    ]);
                });

                Notification::make()->success()->title(__('services.admin.saved'))->send();
            });
    }
}
