<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Services\ServicePricing;

/**
 * What one subscription period costs, as invoice lines: the plan tier's fee
 * (if it has one), each billed service × branches, and the bundle discount.
 * Prices are monthly, so a yearly period is twelve months of them.
 */
final class SubscriptionCharges
{
    public function __construct(private readonly ServicePricing $pricing) {}

    /**
     * @return list<array{description: array{ar: string, en: string}, quantity: int, unit_price_minor: int, amount_minor: int, is_discount: bool}>
     */
    public function lines(Subscription $subscription): array
    {
        $subscription->loadMissing(['plan', 'restaurant']);
        $months = $subscription->interval->months();
        $lines = [];

        if ($subscription->unit_price_minor > 0) {
            $lines[] = [
                'description' => self::both(fn (string $l): string => __('services.invoice.tier', ['plan' => $subscription->plan->getTranslation('name', $l)], $l)),
                'quantity' => $subscription->quantity,
                'unit_price_minor' => $subscription->unit_price_minor,
                'amount_minor' => $subscription->unit_price_minor * $subscription->quantity,
                'is_discount' => false,
            ];
        }

        if ($subscription->restaurant === null) {
            return $lines;
        }

        $quote = $this->pricing->restaurantQuote($subscription->restaurant);

        foreach ($quote['lines'] as $line) {
            if ($line['amount_minor'] === 0) {
                continue; // included at no charge: nothing to bill
            }

            $lines[] = [
                'description' => self::both(fn (string $l): string => __('services.invoice.line', [
                    'service' => __('services.names.'.$line['service']->value, [], $l),
                    'branches' => trans_choice('services.invoice.branches', $quote['branches'], ['count' => $quote['branches']], $l),
                ], $l)),
                'quantity' => $quote['branches'],
                'unit_price_minor' => $line['unit_minor'] * $months,
                'amount_minor' => $line['amount_minor'] * $months,
                'is_discount' => false,
            ];
        }

        if ($quote['discount_minor'] > 0) {
            $percent = rtrim(rtrim(number_format($quote['discount_bp'] / 100, 2, '.', ''), '0'), '.');
            $lines[] = [
                'description' => self::both(fn (string $l): string => __('services.invoice.bundle', ['percent' => $percent], $l)),
                'quantity' => 1,
                'unit_price_minor' => $quote['discount_minor'] * $months,
                'amount_minor' => $quote['discount_minor'] * $months,
                'is_discount' => true,
            ];
        }

        return $lines;
    }

    public function total(Subscription $subscription): int
    {
        return self::sum($this->lines($subscription));
    }

    /**
     * @param  list<array{amount_minor: int, is_discount: bool}>  $lines
     */
    public static function sum(array $lines): int
    {
        $total = 0;

        foreach ($lines as $line) {
            $total += $line['is_discount'] ? -$line['amount_minor'] : $line['amount_minor'];
        }

        return max(0, $total);
    }

    /**
     * @param  callable(string): string  $text
     * @return array{ar: string, en: string}
     */
    public static function both(callable $text): array
    {
        return ['ar' => $text('ar'), 'en' => $text('en')];
    }
}
