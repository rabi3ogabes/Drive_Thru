<?php

declare(strict_types=1);

namespace App\Domain\Loyalty;

use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Ordering\Data\Adjustment;
use App\Domain\Ordering\Data\PricedLine;

/**
 * What a wallet reward takes off this cart, after promotions. Null when it
 * doesn't apply (free item not in the cart, order below the minimum).
 */
final class RewardPricer
{
    /**
     * @param  list<PricedLine>  $lines
     * @param  int  $remainingMinor  what is left to pay after promotions
     */
    public function adjustment(WalletReward $wallet, array $lines, int $remainingMinor): ?Adjustment
    {
        $reward = $wallet->reward;
        $subtotal = array_sum(array_map(fn (PricedLine $l): int => $l->isValid() ? $l->totalMinor() : 0, $lines));

        if (! $wallet->isUsable() || $remainingMinor <= 0 || $subtotal < $reward->min_order_minor) {
            return null;
        }

        $amount = match ($reward->type) {
            RewardType::FreeItem => $this->unitPrice($lines, $reward->product_id),
            RewardType::PercentOff => (int) round($remainingMinor * min(10_000, (int) $reward->value) / 10_000),
            RewardType::FixedOff => (int) $reward->value,
            RewardType::BonusPoints => 0,
        };

        $amount = min($amount, $remainingMinor);

        return $amount > 0 ? new Adjustment(Adjustment::REWARD, $wallet->id, $reward->getTranslations('name'), $amount) : null;
    }

    /**
     * @param  list<PricedLine>  $lines
     */
    private function unitPrice(array $lines, ?int $productId): int
    {
        foreach ($lines as $line) {
            if ($productId !== null && $line->isValid() && $line->product?->id === $productId) {
                return $line->unitPriceMinor + $line->optionsMinor;
            }
        }

        return 0;
    }
}
