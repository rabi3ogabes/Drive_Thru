<?php

declare(strict_types=1);

namespace App\Domain\Promotions;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Data\Adjustment;
use App\Domain\Ordering\Data\PricedLine;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\Redemption;
use App\Support\Lang;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Which promotions a cart gets, and for how much. Pure: it reads, never writes.
 *
 * Eligibility: live (not archived, inside its dates), on the right days and
 * hours in the restaurant's time zone (ranges may cross midnight), at this
 * branch, above the minimum subtotal, first order / birthday (±3 days, any
 * year) when asked, and under its total and per-guest limits (released
 * redemptions don't count). Coupon-only promotions apply only through a code.
 *
 * Amounts: percent of the scoped subtotal; fixed up to the scoped subtotal;
 * buy X get Y gives the cheapest units of each X+Y group; a free item is one
 * unit of that product when it's in the cart; a combo price is the saving
 * against the listed products' single prices; bonus points give points only.
 *
 * Stacking: a promotion that doesn't combine stands alone. The cart gets the
 * bigger of (the best one that doesn't combine) and (all that combine,
 * added up); ties among the ones that don't combine go to higher priority,
 * then the older promotion. A code the guest entered is always applied: alone
 * if it doesn't combine, otherwise together with everything that combines.
 * Bonus points take nothing off, so they never compete and always apply.
 * The total taken off never exceeds the subtotal.
 */
final class PromotionEngine
{
    public const BIRTHDAY_DAYS = 3;

    /**
     * @param  list<PricedLine>  $lines
     * @return array{adjustments: list<Adjustment>, couponError: ?string}
     */
    public function apply(Branch $branch, array $lines, ?Customer $customer, ?Coupon $coupon, CarbonInterface $at): array
    {
        $branch->loadMissing('restaurant');
        $lines = array_values(array_filter($lines, fn (PricedLine $l): bool => $l->isValid()));
        $subtotal = array_sum(array_map(fn (PricedLine $l): int => $l->totalMinor(), $lines));
        $local = CarbonImmutable::instance($at)->setTimezone($branch->restaurant->timezone);

        if ($lines === []) {
            return ['adjustments' => [], 'couponError' => null];
        }

        $couponError = null;
        $couponCandidate = null;

        if ($coupon !== null) {
            $couponError = $this->couponProblem($coupon, $branch, $customer, $subtotal, $local);

            if ($couponError === null) {
                $amount = $this->amount($coupon->promotion, $lines);
                $couponCandidate = $this->adjustment($coupon->promotion, $amount, Adjustment::COUPON);

                if ($amount === 0 && $coupon->promotion->type !== PromotionType::BonusPoints) {
                    $couponError = Lang::text('promotions.coupon_errors.not_applicable');
                    $couponCandidate = null;
                }
            }
        }

        $automatic = Promotion::withoutTenancy()
            ->where('restaurant_id', $branch->restaurant_id)
            ->where('requires_coupon', false)
            ->whereNull('archived_at')
            ->when($couponCandidate !== null, fn ($query) => $query->whereKeyNot($coupon?->promotion_id))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Promotion $p): bool => $this->eligible($p, $branch, $customer, $subtotal, $local))
            ->map(fn (Promotion $p): array => ['promotion' => $p, 'adjustment' => $this->adjustment($p, $this->amount($p, $lines), Adjustment::PROMOTION)])
            ->filter(fn (array $c): bool => $c['adjustment']->amountMinor > 0 || $c['adjustment']->bonusPoints > 0)
            ->values();

        $bonus = $automatic->filter(fn (array $c): bool => $c['promotion']->type === PromotionType::BonusPoints)->pluck('adjustment');
        $discounts = $automatic->reject(fn (array $c): bool => $c['promotion']->type === PromotionType::BonusPoints);
        $stackable = $discounts->filter(fn (array $c): bool => $c['promotion']->stackable)->pluck('adjustment');
        $exclusive = $discounts->reject(fn (array $c): bool => $c['promotion']->stackable)->pluck('adjustment');

        if ($couponCandidate !== null) {
            $chosen = $coupon?->promotion->stackable ? collect([$couponCandidate])->merge($stackable) : collect([$couponCandidate]);
        } else {
            // Candidates arrive ordered by priority then id, so the first of equal amounts wins.
            $best = $exclusive->reduce(fn (?Adjustment $carry, Adjustment $a): Adjustment => $carry === null || $a->amountMinor > $carry->amountMinor ? $a : $carry);
            $chosen = $best !== null && $best->amountMinor > $stackable->sum('amountMinor') ? collect([$best]) : $stackable;
        }

        return ['adjustments' => $this->cap($chosen->merge($bonus)->values(), $subtotal), 'couponError' => $couponError];
    }

    private function couponProblem(Coupon $coupon, Branch $branch, ?Customer $customer, int $subtotal, CarbonImmutable $local): ?string
    {
        $promotion = $coupon->promotion;

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            return Lang::text('promotions.coupon_errors.expired');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return Lang::text('promotions.coupon_errors.used_up');
        }

        if ($customer !== null && $coupon->per_customer_limit !== null
            && $this->usesBy($customer, $promotion->id, $coupon->id) >= $coupon->per_customer_limit) {
            return Lang::text('promotions.coupon_errors.already_used');
        }

        if ($subtotal < $promotion->min_subtotal_minor) {
            return Lang::text('promotions.coupon_errors.min_subtotal', ['amount' => Money::format($promotion->min_subtotal_minor, $branch->restaurant->currency)]);
        }

        return $this->eligible($promotion, $branch, $customer, $subtotal, $local) ? null : Lang::text('promotions.coupon_errors.not_applicable');
    }

    private function eligible(Promotion $p, Branch $branch, ?Customer $customer, int $subtotal, CarbonImmutable $local): bool
    {
        return $p->archived_at === null
            && ($p->starts_at === null || $p->starts_at->lte($local))
            && ($p->ends_at === null || $p->ends_at->gt($local))
            && ($p->branch_ids === null || $p->branch_ids === [] || in_array($branch->id, array_map('intval', $p->branch_ids), true))
            && $subtotal >= $p->min_subtotal_minor
            && ($p->usage_limit === null || $p->used_count < $p->usage_limit)
            && $this->inWindow($p, $local)
            && (! $p->first_order || ($customer !== null && $customer->orders_count === 0))
            && (! $p->birthday || ($customer !== null && $this->nearBirthday($customer, $local)))
            && ($p->per_customer_limit === null || $customer === null || $this->usesBy($customer, $p->id) < $p->per_customer_limit);
    }

    private function inWindow(Promotion $p, CarbonImmutable $local): bool
    {
        $from = $p->time_from;
        $to = $p->time_to;
        $time = $local->format('H:i');
        $day = $local->dayOfWeek;

        if ($from !== null && $to !== null && $from !== $to) {
            if ($from < $to) {
                $inTime = $time >= $from && $time < $to;
            } else {
                // Crosses midnight: the part after midnight belongs to the previous day's window.
                $inTime = $time >= $from || $time < $to;
                $day = $time < $to ? $local->subDay()->dayOfWeek : $day;
            }

            if (! $inTime) {
                return false;
            }
        }

        return $p->days === null || $p->days === [] || in_array($day, array_map('intval', $p->days), true);
    }

    private function nearBirthday(Customer $customer, CarbonImmutable $local): bool
    {
        if ($customer->birthday === null) {
            return false;
        }

        $today = $local->startOfDay();

        foreach ([-1, 0, 1] as $offset) {
            $year = $today->year + $offset;
            $first = CarbonImmutable::create($year, $customer->birthday->month, 1, 0, 0, 0, $local->timezone);

            if ($first === null) {
                continue;
            }

            // 29 February falls on the 28th in other years.
            $day = $first->day(min($customer->birthday->day, $first->daysInMonth));

            if (abs($today->diffInDays($day, false)) <= self::BIRTHDAY_DAYS) {
                return true;
            }
        }

        return false;
    }

    private function usesBy(Customer $customer, int $promotionId, ?int $couponId = null): int
    {
        return Redemption::withoutTenancy()
            ->where('promotion_id', $promotionId)
            ->where('customer_id', $customer->id)
            ->when($couponId !== null, fn ($query) => $query->where('coupon_id', $couponId))
            ->whereNull('released_at')
            ->count();
    }

    /**
     * @param  list<PricedLine>  $lines
     */
    private function amount(Promotion $p, array $lines): int
    {
        $scoped = array_values(array_filter($lines, fn (PricedLine $l): bool => $this->inScope($p, $l)));
        $scopedSubtotal = array_sum(array_map(fn (PricedLine $l): int => $l->totalMinor(), $scoped));

        return match ($p->type) {
            PromotionType::Percent => (int) round($scopedSubtotal * min(10_000, (int) $p->value_bp) / 10_000),
            PromotionType::Fixed => min((int) $p->value_minor, $scopedSubtotal),
            PromotionType::Bogo => $this->bogo($scoped, (int) $p->buy_qty, (int) $p->get_qty),
            PromotionType::FreeItem => $this->unitPrice($lines, $p->free_product_id),
            PromotionType::ComboPrice => $this->combo($lines, $p),
            PromotionType::BonusPoints => 0,
        };
    }

    private function inScope(Promotion $p, PricedLine $line): bool
    {
        $ids = array_map('intval', $p->scope_ids ?? []);

        return match ($p->scope) {
            'categories' => in_array((int) $line->product?->menu_category_id, $ids, true),
            'products' => in_array((int) $line->product?->id, $ids, true),
            default => true,
        };
    }

    /**
     * @param  list<PricedLine>  $scoped
     */
    private function bogo(array $scoped, int $buy, int $get): int
    {
        if ($buy < 1 || $get < 1) {
            return 0;
        }

        $units = [];
        foreach ($scoped as $line) {
            for ($i = 0; $i < $line->quantity; $i++) {
                $units[] = $line->unitPriceMinor + $line->optionsMinor;
            }
        }

        rsort($units);
        $free = 0;

        foreach (array_chunk($units, $buy + $get) as $group) {
            if (count($group) === $buy + $get) {
                $free += array_sum(array_slice($group, $buy));
            }
        }

        return $free;
    }

    /**
     * @param  list<PricedLine>  $lines
     */
    private function unitPrice(array $lines, ?int $productId): int
    {
        foreach ($lines as $line) {
            if ($productId !== null && $line->product?->id === $productId) {
                return $line->unitPriceMinor + $line->optionsMinor;
            }
        }

        return 0;
    }

    /**
     * @param  list<PricedLine>  $lines
     */
    private function combo(array $lines, Promotion $p): int
    {
        $ids = array_values(array_unique(array_map('intval', $p->combo_product_ids ?? [])));

        if ($ids === []) {
            return 0;
        }

        $sum = 0;
        foreach ($ids as $id) {
            $price = $this->unitPrice($lines, $id);

            if ($price === 0) {
                return 0;
            }

            $sum += $price;
        }

        return max(0, $sum - (int) $p->combo_price_minor);
    }

    private function adjustment(Promotion $p, int $amount, string $kind): Adjustment
    {
        return new Adjustment(
            kind: $kind,
            sourceId: $p->id,
            label: $p->getTranslations('name'),
            amountMinor: $p->type === PromotionType::BonusPoints ? 0 : $amount,
            bonusPoints: $p->type === PromotionType::BonusPoints ? (int) $p->value_minor : 0,
        );
    }

    /**
     * @param  Collection<int, Adjustment>  $adjustments
     * @return list<Adjustment>
     */
    private function cap(Collection $adjustments, int $subtotal): array
    {
        $left = $subtotal;
        $capped = [];

        foreach ($adjustments as $a) {
            $amount = min($a->amountMinor, $left);
            $left -= $amount;
            $capped[] = $amount === $a->amountMinor ? $a : $a->withAmount($amount);
        }

        return $capped;
    }
}
