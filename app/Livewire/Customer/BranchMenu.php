<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\BranchMenu as Menu;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\ModifierOption;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\CartPricer;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Support\Lang;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A branch's menu with the product sheet (options, meals, quantity, notes,
 * live price), "make it a meal" and "complete your meal" suggestions, and a
 * sticky bag bar. Every add is validated and priced on the server.
 */
class BranchMenu extends Component
{
    use GuestPage;

    #[Locked]
    public int $branchId;

    /**
     * The product sheet currently open (built on the server).
     *
     * @var array<string, mixed>|null
     */
    public ?array $sheet = null;

    /**
     * After adding: a meal upgrade or "complete your meal" suggestions.
     *
     * @var array<string, mixed>|null
     */
    public ?array $upsell = null;

    #[Url(as: 'item', except: null)]
    public ?int $item = null;

    public function mount(Restaurant $restaurant, string $branch): void
    {
        $this->restaurant = $restaurant;
        $model = $this->findBranch($branch);

        if ($model === null) {
            abort(response()->view('public.branch-missing', ['restaurant' => $restaurant], 404));
        }

        $this->branchId = $model->id;
        $this->rememberSource();

        if ($this->item !== null) {
            $this->openProduct($this->item);
        }
    }

    public function branch(): Branch
    {
        return once(fn (): Branch => Branch::withoutTenancy()->with(['restaurant', 'hours', 'closures', 'lanes'])->findOrFail($this->branchId));
    }

    public function openProduct(int $productId): void
    {
        $product = Product::withoutTenancy()
            ->where('restaurant_id', $this->restaurant->id)
            ->with(['category.schedule', 'schedule', 'modifierGroups.options.nestedGroup.options', 'comboSlots.options.product', 'meal'])
            ->find($productId);

        if ($product === null) {
            $this->item = null;

            return;
        }

        $this->upsell = null;
        $this->item = $product->id;
        $this->sheet = $this->payload($product);
    }

    public function closeSheet(): void
    {
        $this->sheet = null;
        $this->item = null;
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @param  array<int|string, mixed>  $combo
     */
    public function add(int $productId, int $quantity, array $options = [], array $combo = [], ?string $notes = null): void
    {
        if (! $this->restaurant->hasService(Service::DriveThru)) {
            $this->toast(Lang::text('guest.menu.menu_only'), 'error');

            return;
        }

        $line = ['key' => 'check', 'product_id' => $productId, 'quantity' => $quantity, 'options' => $options, 'combo' => $combo, 'notes' => $notes];
        $priced = app(CartPricer::class)->price($this->branch(), [$line]);
        $checked = $priced->lines[0];

        if (! $checked->isValid() || $checked->product === null) {
            $this->toast($checked->error ?? Lang::text('ordering.cart.errors.gone'), 'error');

            return;
        }

        $this->cart()->add($this->restaurant->id, $productId, $checked->quantity, $options, $combo, $checked->notes);
        $this->closeSheet();
        $this->toast(Lang::text('guest.menu.added', ['item' => $checked->product->name]));
        $this->upsell = $this->suggestions($checked->product);
    }

    /** Swaps the item just added for its meal (default sides and drink). */
    public function makeItAMeal(int $itemId, int $mealId): void
    {
        $lines = $this->cart()->lines($this->restaurant->id);
        $line = collect($lines)->last(fn (array $l): bool => (int) $l['product_id'] === $itemId);

        $this->upsell = null;

        if ($line === null) {
            return;
        }

        $this->cart()->setQuantity($this->restaurant->id, (string) $line['key'], (int) $line['quantity'] - 1);
        $this->add($mealId, 1);
        $this->upsell = null;
    }

    public function quickAdd(int $productId): void
    {
        $this->upsell = null;
        $this->openProduct($productId);
    }

    public function dismissUpsell(): void
    {
        $this->upsell = null;
    }

    public function toggleFavorite(int $productId): void
    {
        $customer = $this->customer();

        if ($customer === null) {
            $this->redirectRoute('customer.sign-in', ['restaurant' => $this->restaurant->slug, 'to' => request()->header('Referer')], navigate: true);

            return;
        }

        $exists = Product::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->whereKey($productId)->exists();

        if (! $exists) {
            return;
        }

        $result = $customer->favorites()->toggle([$productId => ['restaurant_id' => $this->restaurant->id]]);

        if ($this->sheet !== null) {
            $this->sheet['favorite'] = $result['attached'] !== [];
        }

        $this->toast($result['attached'] !== [] ? Lang::text('guest.menu.favorited') : Lang::text('guest.menu.unfavorited'));
    }

    public function render(): View
    {
        $branch = $this->branch();
        $schedule = app(BranchSchedule::class);
        $lines = $this->cart()->lines($this->restaurant->id);
        $bag = $lines === [] ? null : app(CartPricer::class)->price($branch, $lines);

        return $this->page('livewire.customer.branch-menu', [
            'branch' => $branch,
            'schedule' => $schedule,
            'state' => $schedule->state($branch),
            'sections' => app(Menu::class)->sections($branch),
            'customer' => $this->customer(),
            'bag' => $bag,
            'ordering' => $this->restaurant->hasService(Service::DriveThru),
        ], $branch->name);
    }

    /**
     * Everything the sheet needs to work instantly in the browser.
     *
     * @return array<string, mixed>
     */
    private function payload(Product $product): array
    {
        $branch = $this->branch();
        $menu = app(Menu::class);
        $settings = $menu->settingsFor($branch);
        $offer = $menu->offer($product, $branch, $settings->get($product->id));
        $currency = $this->restaurant->currency;

        $group = fn (ModifierGroup $g): array => [
            'id' => $g->id,
            'name' => $g->name,
            'kind' => $g->kind->value,
            'min' => $g->min_select,
            'max' => $g->max_select,
            'options' => $g->options->filter(fn (ModifierOption $o): bool => $o->is_available)->map(fn (ModifierOption $o): array => [
                'id' => $o->id,
                'name' => $o->name,
                'price' => $o->price_delta_minor,
                'price_label' => $o->price_delta_minor !== 0 ? ($o->price_delta_minor > 0 ? '+' : '−').Money::format(abs($o->price_delta_minor), $currency) : null,
                'calories' => $o->calories_delta,
                'default' => $o->is_default,
                'nested' => $o->nested_group_id,
            ])->values()->all(),
        ];

        $nested = [];
        foreach ($product->modifierGroups as $g) {
            foreach ($g->options as $o) {
                if ($o->nested_group_id !== null && $o->nestedGroup !== null) {
                    $nested[$o->nested_group_id] = $group($o->nestedGroup);
                }
            }
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'ingredients' => $product->ingredients,
            'image' => $product->imageUrl(800),
            'icon' => $product->category->icon ?? '🍽️',
            'calories' => $product->calories,
            'allergens' => $product->allergens->map(fn ($a): array => ['symbol' => $a->symbol(), 'label' => $a->getLabel()])->values()->all(),
            'tags' => $product->tags->map(fn ($t): array => ['symbol' => $t->symbol(), 'label' => $t->getLabel()])->values()->all(),
            'price' => $offer->priceMinor,
            'base_price' => $offer->basePriceMinor,
            'available' => $offer->isAvailable(),
            'unavailable' => $offer->unavailable?->getLabel(),
            'is_combo' => $product->isCombo(),
            'groups' => $product->modifierGroups->map($group)->values()->all(),
            'nested' => $nested,
            'slots' => $product->comboSlots->map(fn (ComboSlot $slot): array => [
                'id' => $slot->id,
                'name' => $slot->name,
                'options' => $slot->options->map(fn (ComboSlotOption $o): array => [
                    'id' => $o->id,
                    'name' => $o->product->name,
                    'price' => $o->price_delta_minor,
                    'price_label' => $o->price_delta_minor > 0 ? '+'.Money::format($o->price_delta_minor, $currency) : null,
                    'default' => $o->is_default,
                    'available' => $menu->offer($o->product, $branch, $settings->get($o->product_id))->isAvailable(),
                ])->values()->all(),
            ])->values()->all(),
            'favorite' => $this->customer()?->favorites()->whereKey($product->id)->exists() ?? false,
            'decimals' => Money::decimals($currency),
            'currency' => $currency,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function suggestions(Product $product): ?array
    {
        $branch = $this->branch();
        $menu = app(Menu::class);
        $settings = $menu->settingsFor($branch);

        if ($product->meal_product_id !== null) {
            $meal = Product::withoutTenancy()->with('comboSlots.options.product')->find($product->meal_product_id);

            if ($meal !== null && $menu->offer($meal, $branch, $settings->get($meal->id))->isAvailable()) {
                $savings = $menu->comboSavings($meal, $branch, $settings);

                return [
                    'type' => 'meal',
                    'item_id' => $product->id,
                    'meal_id' => $meal->id,
                    'meal' => $meal->name,
                    'price' => Money::format($menu->offer($meal, $branch, $settings->get($meal->id))->priceMinor, $this->restaurant->currency),
                    'savings' => $savings > 0 ? Money::format($savings, $this->restaurant->currency) : null,
                ];
            }
        }

        $inBag = array_map(fn (array $l): int => (int) $l['product_id'], $this->cart()->lines($this->restaurant->id));
        $recommended = $product->recommendations()->get()
            ->filter(fn (Product $p): bool => ! in_array($p->id, $inBag, true) && $menu->offer($p, $branch, $settings->get($p->id))->isAvailable())
            ->take(3)
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => Money::format($menu->offer($p, $branch, $settings->get($p->id))->priceMinor, $this->restaurant->currency),
                'image' => $p->imageUrl(400),
            ])
            ->values()
            ->all();

        return $recommended === [] ? null : ['type' => 'complete', 'items' => $recommended];
    }
}
