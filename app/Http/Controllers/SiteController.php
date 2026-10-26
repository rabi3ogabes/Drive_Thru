<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Models\Plan;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\ServicePricing;
use App\Support\Money;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The public website: home, one page per service and pricing. Prices, demo
 * length and bundle discounts come from the platform's price list, so every
 * page, the calculator and the invoices agree.
 */
final class SiteController
{
    public function __construct(private readonly ServicePricing $pricing) {}

    public function home(): View
    {
        return view('home', $this->shared());
    }

    public function service(string $service): View
    {
        $current = Service::fromSlug($service);
        abort_if($current === null, 404);

        return view('site.service', [...$this->shared(), 'service' => $current]);
    }

    public function pricing(): View
    {
        return view('site.pricing', [
            ...$this->shared(),
            'tiers' => Plan::query()->available()->where('is_public', true)->get()->map(fn (Plan $plan): array => [
                'name' => $plan->name,
                'description' => $plan->description,
                'fee' => $plan->price_minor,
                'fee_label' => $plan->price_minor === null ? null : Money::format($plan->price_minor, $plan->currency),
                'branches' => $plan->limit('branches'),
                'employees' => $plan->limit('employees'),
                'addons' => collect(PlanFeature::addOns())->filter(fn (PlanFeature $f): bool => $plan->includes($f))->map(fn (PlanFeature $f): string => $f->getLabel())->values()->all(),
                'highlighted' => $plan->is_highlighted,
            ]),
        ]);
    }

    /** The public pages for search engines, in both languages. */
    public function sitemap(): Response
    {
        $paths = ['/', '/pricing', ...array_map(fn (Service $s): string => '/services/'.$s->slug(), Service::cases())];

        return response()->view('site.sitemap', ['paths' => $paths, 'locales' => array_keys((array) config('locales.supported'))])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * @return array{services: Collection<string, array{key: string, label: string, price: int, price_label: string, demo_days: int, for_sale: bool}>, pricing: array{currency: string, decimals: int, symbol: string, two: int, three: int}, demoDays: int}
     */
    private function shared(): array
    {
        $currency = $this->pricing->currency();

        $services = array_map(fn (Service $service): array => [
            'key' => $service->value,
            'label' => $service->getLabel(),
            'price' => $this->pricing->listPriceMinor($service),
            'price_label' => Money::format($this->pricing->listPriceMinor($service), $currency),
            'demo_days' => $this->pricing->demoDays($service),
            'for_sale' => $this->pricing->forSale($service),
        ], Service::cases());

        return [
            'services' => collect($services)->keyBy('key'),
            'pricing' => [
                'currency' => $currency,
                'decimals' => Money::decimals($currency),
                'symbol' => trim(explode("\u{00A0}", Money::format(0, $currency))[1] ?? $currency),
                'two' => $this->pricing->bundleDiscountBp(2),
                'three' => $this->pricing->bundleDiscountBp(3),
            ],
            'demoDays' => max(array_column($services, 'demo_days') ?: [14]),
        ];
    }
}
