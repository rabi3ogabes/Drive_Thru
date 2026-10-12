<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\PointsRedemption;
use App\Domain\Loyalty\RewardPricer;
use App\Domain\Menu\BranchMenu;
use App\Domain\Menu\Models\BranchProduct;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\ModifierOption;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Data\Adjustment;
use App\Domain\Ordering\Data\CheckoutBenefits;
use App\Domain\Ordering\Data\PricedCart;
use App\Domain\Ordering\Data\PricedLine;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\PromotionEngine;
use App\Support\Lang;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prices and validates cart lines against the live menu at one branch:
 * availability, branch price and discount, option rules (min/max, nested
 * follow-ups, availability), meal slots, then service fee and tax.
 *
 * Lines come from the browser as ids only:
 * ['key', 'product_id', 'quantity', 'options' => [groupId => [optionId…]],
 *  'combo' => [slotId => comboSlotOptionId], 'notes'].
 */
final class CartPricer
{
    public const MAX_QUANTITY = 20;

    public function __construct(
        private readonly BranchMenu $menu,
        private readonly KitchenLoad $load,
        private readonly PromotionEngine $promotions,
        private readonly RewardPricer $rewards,
        private readonly PointsRedemption $redemption,
    ) {}

    /**
     * Discounts apply in a fixed order: promotions and the coupon, then a
     * wallet reward, then points; fee and tax are on what is left.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function price(Branch $branch, array $lines, ?CarbonInterface $at = null, ?CheckoutBenefits $benefits = null): PricedCart
    {
        $branch->loadMissing('restaurant');
        $restaurant = $branch->restaurant;
        $settings = $this->menu->settingsFor($branch);
        $products = $this->products($branch, $lines);

        $priced = [];
        $maxPrep = 0;

        foreach ($lines as $line) {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $priced[] = $this->line($branch, $product, $line, $settings, $at);

            if ($product !== null && $product->prep_minutes !== null) {
                $maxPrep = max($maxPrep, $product->prep_minutes);
            }
        }

        $subtotal = array_sum(array_map(fn (PricedLine $l): int => $l->isValid() ? $l->totalMinor() : 0, $priced));
        $benefit = $this->benefits($branch, $priced, $subtotal, $at, $benefits);
        $discount = array_sum(array_map(fn (Adjustment $a): int => $a->amountMinor, $benefit['adjustments']));
        $net = $subtotal - $discount;
        $fee = (int) round($net * $restaurant->service_fee_bp / 10_000);
        $tax = (int) round(($net + $fee) * $restaurant->tax_rate_bp / 10_000);

        return new PricedCart(
            lines: $priced,
            subtotalMinor: $subtotal,
            discountMinor: $discount,
            serviceFeeMinor: $fee,
            taxMinor: $tax,
            totalMinor: $net + $fee + $tax,
            prepMinutes: max($this->prepFor($branch, $at), $maxPrep),
            currency: $restaurant->currency,
            adjustments: $benefit['adjustments'],
            pointsRedeemed: $benefit['points'],
            pointsToEarn: $this->pointsToEarn($branch, $benefits?->customer, $net, $benefit['adjustments']),
            benefitError: $benefit['error'],
        );
    }

    /**
     * @param  list<PricedLine>  $lines
     * @return array{adjustments: list<Adjustment>, points: int, error: ?string}
     */
    private function benefits(Branch $branch, array $lines, int $subtotal, ?CarbonInterface $at, ?CheckoutBenefits $benefits): array
    {
        $restaurant = $branch->restaurant;
        $customer = $benefits?->customer;
        $adjustments = [];
        $error = null;
        $points = 0;
        $code = $benefits?->couponCode !== null && trim($benefits->couponCode) !== '' ? $benefits->couponCode : null;

        if ($restaurant->hasFeature(PlanFeature::Promotions)) {
            $coupon = $code === null ? null : Coupon::findCode($restaurant->id, $code);
            $error = $code !== null && ($coupon === null || $coupon->promotion->archived_at !== null) ? Lang::text('promotions.coupon_errors.unknown') : null;
            $applied = $this->promotions->apply($branch, $lines, $customer, $error === null ? $coupon : null, $at ?? now());
            $adjustments = $applied['adjustments'];
            $error ??= $applied['couponError'];
        } elseif ($code !== null) {
            $error = Lang::text('promotions.coupon_errors.unavailable');
        }

        if ($customer === null || ! $restaurant->hasFeature(PlanFeature::Loyalty)) {
            return ['adjustments' => $adjustments, 'points' => 0, 'error' => $error];
        }

        if ($benefits?->walletRewardId !== null) {
            $wallet = WalletReward::withoutTenancy()->with('reward')->where('customer_id', $customer->id)->find($benefits->walletRewardId);
            $reward = $wallet === null ? null : $this->rewards->adjustment($wallet, $lines, $this->left($subtotal, $adjustments));

            if ($reward !== null) {
                $adjustments[] = $reward;
            } else {
                $error ??= Lang::text('loyalty.reward_errors.not_applicable');
            }
        }

        $program = $this->program($restaurant->id);

        if ($program !== null && $benefits !== null && $benefits->pointsToRedeem > 0) {
            $clamped = $this->redemption->clamp($program, $customer, $benefits->pointsToRedeem, $this->left($subtotal, $adjustments));

            if ($clamped['points'] > 0) {
                $points = $clamped['points'];
                $adjustments[] = new Adjustment(Adjustment::POINTS, 0, [
                    'en' => Lang::text('loyalty.points_line', ['points' => number_format($points)], 'en'),
                    'ar' => Lang::text('loyalty.points_line', ['points' => number_format($points)], 'ar'),
                ], $clamped['discountMinor']);
            }
        }

        return ['adjustments' => $adjustments, 'points' => $points, 'error' => $error];
    }

    /**
     * What is still to pay after the discounts so far.
     *
     * @param  list<Adjustment>  $adjustments
     */
    private function left(int $subtotal, array $adjustments): int
    {
        return $subtotal - array_sum(array_map(fn (Adjustment $a): int => $a->amountMinor, $adjustments));
    }

    /**
     * About how many points this order will earn (the exact award happens at pick-up).
     *
     * @param  list<Adjustment>  $adjustments
     */
    private function pointsToEarn(Branch $branch, ?Customer $customer, int $net, array $adjustments): int
    {
        if ($customer === null || ! $branch->restaurant->hasFeature(PlanFeature::Loyalty)) {
            return 0;
        }

        $program = $this->program($branch->restaurant_id);

        if ($program === null || ! $program->isActive()) {
            return 0;
        }

        $multiplier = $customer->tier_id === null ? 10_000 : (int) (Tier::withoutTenancy()->whereKey($customer->tier_id)->value('earn_multiplier_bp') ?? 10_000);

        return $program->pointsFor($net, $branch->restaurant->currency, $multiplier)
            + array_sum(array_map(fn (Adjustment $a): int => $a->bonusPoints, $adjustments));
    }

    private function program(int $restaurantId): ?LoyaltyProgram
    {
        return LoyaltyProgram::withoutTenancy()->where('restaurant_id', $restaurantId)->first();
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  \Illuminate\Support\Collection<int, BranchProduct>  $settings
     */
    private function line(Branch $branch, ?Product $product, array $line, $settings, ?CarbonInterface $at): PricedLine
    {
        $key = (string) ($line['key'] ?? '');
        $quantity = max(1, min(self::MAX_QUANTITY, (int) ($line['quantity'] ?? 1)));
        $notes = isset($line['notes']) && is_string($line['notes']) && trim($line['notes']) !== '' ? mb_substr(trim($line['notes']), 0, 200) : null;

        if ($product === null) {
            return new PricedLine($key, null, $quantity, 0, 0, [], $notes, Lang::text('ordering.cart.errors.gone'));
        }

        $offer = $this->menu->offer($product, $branch, $settings->get($product->id), $at);

        if (! $offer->isAvailable()) {
            return new PricedLine($key, $product, $quantity, $offer->priceMinor, 0, [], $notes, $offer->unavailable?->getLabel(), $offer->basePriceMinor);
        }

        if ($offer->stockLeft !== null && $offer->stockLeft < $quantity) {
            return new PricedLine($key, $product, $quantity, $offer->priceMinor, 0, [], $notes, Lang::text('ordering.cart.errors.stock', ['count' => $offer->stockLeft]), $offer->basePriceMinor);
        }

        $options = [];
        $optionsMinor = 0;
        $error = null;

        try {
            if ($product->isCombo()) {
                [$options, $optionsMinor] = $this->comboChoices($branch, $product, (array) ($line['combo'] ?? []), $settings, $at);
            }

            [$modifierOptions, $modifierMinor] = $this->modifierChoices($product, (array) ($line['options'] ?? []));
            $options = array_merge($options, $modifierOptions);
            $optionsMinor += $modifierMinor;
        } catch (InvalidChoice $e) {
            $error = $e->getMessage();
        }

        return new PricedLine($key, $product, $quantity, $offer->priceMinor, $optionsMinor, $options, $notes, $error, $offer->basePriceMinor);
    }

    /**
     * @param  array<int|string, mixed>  $selections  groupId => optionId[]
     * @return array{0: list<array{kind: string, group_id: int, option_id: int, group: array<string, string>, option: array<string, string>, price_minor: int}>, 1: int}
     *
     * @throws InvalidChoice
     */
    public function modifierChoices(Product $product, array $selections): array
    {
        $groups = $product->modifierGroups->keyBy('id');
        $known = $groups->keys()->all();
        $chosen = [];
        $minor = 0;

        // Nested follow-up groups only count when the option that opens them is chosen.
        $queue = $groups->values()->all();
        $seen = [];

        while ($queue !== []) {
            /** @var ModifierGroup $group */
            $group = array_shift($queue);

            if (isset($seen[$group->id])) {
                continue;
            }
            $seen[$group->id] = true;

            $ids = array_values(array_unique(array_map('intval', (array) ($selections[$group->id] ?? $selections[(string) $group->id] ?? $this->defaults($group)))));
            $options = $group->options->keyBy('id');

            foreach ($ids as $id) {
                $option = $options->get($id);

                if (! $option instanceof ModifierOption || ! $option->is_available) {
                    throw new InvalidChoice(Lang::text('ordering.cart.errors.option', ['group' => $group->name]));
                }
            }

            $count = count($ids);

            if ($count < $group->min_select || ($group->max_select !== null && $count > $group->max_select)) {
                throw new InvalidChoice(Lang::text('ordering.cart.errors.rule', ['group' => $group->name]));
            }

            foreach ($ids as $id) {
                /** @var ModifierOption $option */
                $option = $options->get($id);
                $chosen[] = [
                    'kind' => 'option',
                    'group_id' => $group->id,
                    'option_id' => $option->id,
                    'group' => $group->getTranslations('name'),
                    'option' => $option->getTranslations('name'),
                    'price_minor' => $option->price_delta_minor,
                ];
                $minor += $option->price_delta_minor;

                // One level of follow-up questions (the editor offers no deeper nesting).
                if ($groups->has($group->id) && $option->nested_group_id !== null && $option->nestedGroup !== null) {
                    $known[] = $option->nested_group_id;
                    $queue[] = $option->nestedGroup;
                }
            }
        }

        foreach (array_keys($selections) as $groupId) {
            if (! in_array((int) $groupId, $known, true)) {
                throw new InvalidChoice(Lang::text('ordering.cart.errors.options_changed'));
            }
        }

        return [$chosen, $minor];
    }

    /**
     * @param  array<int|string, mixed>  $choices  slotId => comboSlotOptionId
     * @param  \Illuminate\Support\Collection<int, BranchProduct>  $settings
     * @return array{0: list<array{kind: string, group_id: int, option_id: int, group: array<string, string>, option: array<string, string>, price_minor: int}>, 1: int}
     *
     * @throws InvalidChoice
     */
    private function comboChoices(Branch $branch, Product $combo, array $choices, $settings, ?CarbonInterface $at): array
    {
        $chosen = [];
        $minor = 0;

        foreach ($combo->comboSlots as $slot) {
            /** @var ComboSlot $slot */
            $optionId = (int) ($choices[$slot->id] ?? $choices[(string) $slot->id] ?? 0);
            $option = $optionId !== 0
                ? $slot->options->firstWhere('id', $optionId)
                : ($slot->options->first(fn (ComboSlotOption $o): bool => $o->is_default) ?? $slot->options->first());

            if (! $option instanceof ComboSlotOption) {
                throw new InvalidChoice(Lang::text('ordering.cart.errors.option', ['group' => $slot->name]));
            }

            if (! $this->menu->offer($option->product, $branch, $settings->get($option->product_id), $at)->isAvailable()) {
                throw new InvalidChoice(Lang::text('ordering.cart.errors.meal_part', ['item' => $option->product->name]));
            }

            $chosen[] = [
                'kind' => 'combo',
                'group_id' => $slot->id,
                'option_id' => $option->id,
                'group' => $slot->getTranslations('name'),
                'option' => $option->product->getTranslations('name'),
                'price_minor' => $option->price_delta_minor,
            ];
            $minor += $option->price_delta_minor;
        }

        return [$chosen, $minor];
    }

    /**
     * @return list<int>
     */
    public function defaults(ModifierGroup $group): array
    {
        return array_values($group->options
            ->filter(fn (ModifierOption $o): bool => $o->is_default && $o->is_available)
            ->map(fn (ModifierOption $o): int => $o->id)
            ->all());
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return Collection<int, Product>
     */
    private function products(Branch $branch, array $lines): Collection
    {
        $ids = array_values(array_unique(array_map(fn (array $l): int => (int) ($l['product_id'] ?? 0), $lines)));

        return Product::withoutTenancy()
            ->where('restaurant_id', $branch->restaurant_id)
            ->whereKey($ids)
            ->with([
                'category.schedule',
                'schedule',
                'modifierGroups.options.nestedGroup.options',
                'comboSlots.options.product.category.schedule',
                'comboSlots.options.product.schedule',
            ])
            ->get()
            ->keyBy('id');
    }

    /** Live kitchen load for orders starting now; the branch's set time for later starts. */
    private function prepFor(Branch $branch, ?CarbonInterface $at): int
    {
        if ($at === null || $at->lessThanOrEqualTo(now()->addMinutes(2))) {
            return $this->load->estimate($branch);
        }

        return $branch->currentPrepMinutes($at);
    }
}
