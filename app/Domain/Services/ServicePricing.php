<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Branches\Models\Branch;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Models\ServicePrice;
use App\Domain\Tenancy\Models\Restaurant;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The one place service money is worked out: the price list, the bundle
 * discount, a restaurant's monthly total and the website's calculator all
 * come from here, so they never disagree.
 */
final class ServicePricing
{
    /** @var Collection<string, ServicePrice>|null */
    private ?Collection $prices = null;

    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * @return Collection<string, ServicePrice> keyed by service value, in display order
     */
    public function prices(): Collection
    {
        return $this->prices ??= ServicePrice::query()->orderBy('sort')->get()->keyBy(fn (ServicePrice $p): string => $p->service->value);
    }

    public function price(Service $service): ?ServicePrice
    {
        return $this->prices()->get($service->value);
    }

    public function forSale(Service $service): bool
    {
        return $this->price($service)->is_active ?? false;
    }

    public function listPriceMinor(Service $service): int
    {
        return $this->price($service)->price_minor ?? 0;
    }

    public function demoDays(Service $service): int
    {
        return max(1, $this->price($service)->demo_days ?? 14);
    }

    public function currency(): string
    {
        return $this->settings->string('billing.currency') ?? 'QAR';
    }

    /** Discount in basis points for this many paid services. */
    public function bundleDiscountBp(int $services): int
    {
        return match (true) {
            $services >= 3 => max(0, min(10_000, $this->settings->int('billing.bundle_three_bp'))),
            $services === 2 => max(0, min(10_000, $this->settings->int('billing.bundle_two_bp'))),
            default => 0,
        };
    }

    /** Branches a restaurant pays for: every branch it has, at least one. */
    public function billableBranches(Restaurant $restaurant): int
    {
        return max(1, Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->count());
    }

    /**
     * A monthly quote: each service's unit price × branches, then the bundle
     * discount on the services total.
     *
     * @param  array<string, int>  $unitPrices  service value => price per branch per month (minor)
     * @return array{lines: list<array{service: Service, unit_minor: int, amount_minor: int}>, subtotal_minor: int, discount_bp: int, discount_minor: int, total_minor: int, branches: int}
     */
    public function quote(array $unitPrices, int $branches): array
    {
        $branches = max(1, $branches);
        $lines = [];

        foreach (Service::cases() as $service) {
            if (! array_key_exists($service->value, $unitPrices)) {
                continue;
            }

            $unit = max(0, $unitPrices[$service->value]);
            $lines[] = ['service' => $service, 'unit_minor' => $unit, 'amount_minor' => $unit * $branches];
        }

        $subtotal = array_sum(array_column($lines, 'amount_minor'));
        $discountBp = $this->bundleDiscountBp(count($lines));
        $discount = Money::percentOf($subtotal, $discountBp);

        return [
            'lines' => $lines,
            'subtotal_minor' => $subtotal,
            'discount_bp' => $discountBp,
            'discount_minor' => $discount,
            'total_minor' => $subtotal - $discount,
            'branches' => $branches,
        ];
    }

    /**
     * The list-price quote for a set of services (the website calculator).
     *
     * @param  list<Service>  $services
     * @return array{lines: list<array{service: Service, unit_minor: int, amount_minor: int}>, subtotal_minor: int, discount_bp: int, discount_minor: int, total_minor: int, branches: int}
     */
    public function listQuote(array $services, int $branches): array
    {
        $prices = [];

        foreach ($services as $service) {
            if ($this->forSale($service)) {
                $prices[$service->value] = $this->listPriceMinor($service);
            }
        }

        return $this->quote($prices, $branches);
    }

    /**
     * What a restaurant pays per month for the services it is billed for
     * (demos are free), at the prices it was given.
     *
     * @return array{lines: list<array{service: Service, unit_minor: int, amount_minor: int}>, subtotal_minor: int, discount_bp: int, discount_minor: int, total_minor: int, branches: int}
     */
    public function restaurantQuote(Restaurant $restaurant, bool $includeKeptDemos = false): array
    {
        $prices = [];

        foreach ($restaurant->services()->withoutTenancy()->get() as $row) {
            if ($row->status->isBilled() || ($includeKeptDemos && $row->keep_after_demo && $row->isOn())) {
                $prices[$row->service->value] = $row->unit_price_minor;
            }
        }

        return $this->quote($prices, $this->billableBranches($restaurant));
    }
}
