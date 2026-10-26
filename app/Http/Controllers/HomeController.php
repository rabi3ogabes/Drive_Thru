<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Services\Enums\Service;
use App\Domain\Services\ServicePricing;
use App\Support\Money;
use Illuminate\View\View;

/**
 * The public website's home page. Service prices, demo length and bundle
 * discounts come from the platform's price list, so the page, the
 * calculator and the invoices never disagree.
 */
final class HomeController
{
    public function __invoke(ServicePricing $pricing): View
    {
        $currency = $pricing->currency();

        $services = array_map(fn (Service $service): array => [
            'key' => $service->value,
            'label' => $service->getLabel(),
            'price' => $pricing->listPriceMinor($service),
            'price_label' => Money::format($pricing->listPriceMinor($service), $currency),
            'demo_days' => $pricing->demoDays($service),
            'for_sale' => $pricing->forSale($service),
        ], Service::cases());

        return view('home', [
            'services' => collect($services)->keyBy('key'),
            'pricing' => [
                'currency' => $currency,
                'decimals' => Money::decimals($currency),
                'symbol' => trim(explode("\u{00A0}", Money::format(0, $currency))[1] ?? $currency),
                'two' => $pricing->bundleDiscountBp(2),
                'three' => $pricing->bundleDiscountBp(3),
            ],
            'demoDays' => max(array_column($services, 'demo_days') ?: [14]),
        ]);
    }
}
