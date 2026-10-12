<?php

declare(strict_types=1);

namespace App\Domain\Menu;

use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Data\ProductOffer;
use App\Domain\Menu\Enums\Unavailability;
use App\Domain\Menu\Models\BranchProduct;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The menu as one branch serves it, right now: availability (with reasons),
 * branch prices and discounts, today's stock and combo savings.
 *
 * The restaurant-wide catalog is cached (and invalidated on any menu change);
 * the fast-moving branch settings (stock, out of stock today) are read live.
 */
final class BranchMenu
{
    /**
     * Visible sections with their products, fully loaded for rendering.
     *
     * @return EloquentCollection<int, MenuCategory>
     */
    public function catalog(Restaurant $restaurant): EloquentCollection
    {
        return MenuCache::remember($restaurant->id, 'catalog', fn (): EloquentCollection => MenuCategory::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->with([
                'schedule',
                'products' => fn ($query) => $query->with([
                    'schedule',
                    'modifierGroups.options',
                    'comboSlots.options.product',
                    'meal',
                ]),
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get());
    }

    /**
     * The guest menu for a branch: visible sections, each with the products a
     * guest should see (orderable first, then greyed-out "sold out" or "later"
     * ones), popular items leading when the layout asks for it.
     *
     * @return list<array{category: MenuCategory, items: list<array{product: Product, offer: ProductOffer, savings: int}>}>
     */
    public function sections(Branch $branch, ?CarbonInterface $at = null): array
    {
        $branch->loadMissing('restaurant');
        $restaurant = $branch->restaurant;
        $layout = MenuLayout::for($restaurant);
        $settings = $this->settingsFor($branch);
        $sections = [];

        foreach ($this->catalog($restaurant) as $category) {
            if (! $category->is_visible) {
                continue;
            }

            $items = [];

            foreach ($category->products as $product) {
                $product->setRelation('category', $category);
                $offer = $this->offer($product, $branch, $settings->get($product->id), $at);

                if ($offer->unavailable !== null && ! $offer->unavailable->isShownToGuests()) {
                    continue;
                }

                $items[] = [
                    'product' => $product,
                    'offer' => $offer,
                    'savings' => $product->isCombo() ? $this->comboSavings($product, $branch, $settings) : 0,
                ];
            }

            if ($items === []) {
                continue;
            }

            usort($items, fn (array $a, array $b): int => [
                $a['offer']->isAvailable() ? 0 : 1,
                $layout->popularFirst && $a['product']->is_popular ? 0 : 1,
                $a['product']->sort,
            ] <=> [
                $b['offer']->isAvailable() ? 0 : 1,
                $layout->popularFirst && $b['product']->is_popular ? 0 : 1,
                $b['product']->sort,
            ]);

            $sections[] = ['category' => $category, 'items' => $items];
        }

        // Sections with nothing to order right now (e.g. breakfast in the
        // evening) move to the end; otherwise the owner's order is kept.
        $orderable = fn (array $section): bool => $section['items'][0]['offer']->isAvailable();
        $sections = array_merge(
            array_values(array_filter($sections, $orderable)),
            array_values(array_filter($sections, fn (array $section): bool => ! $orderable($section))),
        );

        return $sections;
    }

    /**
     * Branch settings keyed by product id (one query, never cached).
     *
     * @return Collection<int, BranchProduct>
     */
    public function settingsFor(Branch $branch): Collection
    {
        return BranchProduct::withoutTenancy()->where('branch_id', $branch->id)->get()->keyBy('product_id');
    }

    public function offer(Product $product, Branch $branch, ?BranchProduct $setting = null, ?CarbonInterface $at = null): ProductOffer
    {
        $at = CarbonImmutable::instance($at ?? now());
        $local = $at->setTimezone($this->timezone($branch));

        $base = $setting->price_override_minor ?? $product->price_minor;
        $price = $product->finalPriceMinor($base);
        $stock = $setting !== null ? $this->stockLeft($setting, $local->toDateString()) : null;
        $low = $stock !== null && $setting?->low_stock_threshold !== null && $stock <= $setting->low_stock_threshold;

        return new ProductOffer($this->reason($product, $setting, $at, $local, $stock), $base, $price, $stock, $low);
    }

    /**
     * Today's remaining stock, or null when the branch doesn't count it.
     * A count left over from an earlier day refills to the daily amount.
     */
    public function stockLeft(BranchProduct $setting, string $localDate): ?int
    {
        if ($setting->daily_stock === null) {
            return null;
        }

        if ($setting->stock_date === null || $setting->stock_date->toDateString() !== $localDate) {
            return $setting->daily_stock;
        }

        return max(0, (int) $setting->stock_remaining);
    }

    /** The branch's next local midnight, as a UTC moment. */
    public function nextMidnight(Branch $branch, ?CarbonInterface $at = null): CarbonImmutable
    {
        return CarbonImmutable::instance($at ?? now())
            ->setTimezone($this->timezone($branch))
            ->addDay()
            ->startOfDay()
            ->utc();
    }

    public function localDate(Branch $branch, ?CarbonInterface $at = null): string
    {
        return CarbonImmutable::instance($at ?? now())->setTimezone($this->timezone($branch))->toDateString();
    }

    /**
     * What a guest saves by ordering the combo instead of its default items
     * separately, at this branch. Zero when there is no saving.
     *
     * @param  Collection<int, BranchProduct>|null  $settings
     */
    public function comboSavings(Product $combo, Branch $branch, ?Collection $settings = null): int
    {
        if (! $combo->isCombo()) {
            return 0;
        }

        $settings ??= $this->settingsFor($branch);
        $combo->loadMissing('comboSlots.options.product');

        $separately = $combo->comboSlots->sum(function (ComboSlot $slot) use ($branch, $settings): int {
            $default = $slot->options->first(fn (ComboSlotOption $option): bool => $option->is_default) ?? $slot->options->first();

            return $default === null ? 0 : $this->offer($default->product, $branch, $settings->get($default->product_id))->priceMinor;
        });

        $comboPrice = $this->offer($combo, $branch, $settings->get($combo->id))->priceMinor;

        return max(0, $separately - $comboPrice);
    }

    private function reason(Product $product, ?BranchProduct $setting, CarbonImmutable $at, CarbonImmutable $local, ?int $stock): ?Unavailability
    {
        $category = $product->relationLoaded('category') ? $product->category : null;

        return match (true) {
            ! $product->is_available => Unavailability::Disabled,
            $category !== null && ! $category->is_visible => Unavailability::Hidden,
            $setting !== null && ! $setting->is_available => Unavailability::NotServedHere,
            $setting?->out_of_stock_until !== null && $setting->out_of_stock_until->greaterThan($at) => Unavailability::OutOfStockToday,
            $stock === 0 => Unavailability::SoldOut,
            ! $this->inSchedule($product, $category, $local) => Unavailability::NotNow,
            default => null,
        };
    }

    private function inSchedule(Product $product, ?MenuCategory $category, CarbonImmutable $local): bool
    {
        if ($product->menu_schedule_id !== null) {
            $product->loadMissing('schedule');
            $schedule = $product->schedule;
        } elseif ($category?->menu_schedule_id !== null) {
            $category->loadMissing('schedule');
            $schedule = $category->schedule;
        } else {
            $schedule = null;
        }

        return $schedule === null || $schedule->isActiveAt($local);
    }

    private function timezone(Branch $branch): string
    {
        $branch->loadMissing('restaurant');

        return $branch->restaurant->timezone;
    }
}
