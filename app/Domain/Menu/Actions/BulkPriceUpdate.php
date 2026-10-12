<?php

declare(strict_types=1);

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\MenuCache;
use App\Domain\Menu\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Raise or lower many prices at once, by a percentage or a fixed amount,
 * rounded to a friendly step (e.g. to the nearest 0.50). Preview first,
 * then apply exactly what was previewed.
 */
final class BulkPriceUpdate
{
    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    /**
     * @param  iterable<Product>  $products
     * @param  float  $value  percent (e.g. 5 or -10) or amount in minor units
     * @param  int  $roundTo  minor-unit step to round to (1 = no rounding)
     * @return Collection<int, array{product: Product, from: int, to: int}>
     */
    public function preview(iterable $products, string $mode, float $value, int $roundTo = 1): Collection
    {
        if (! in_array($mode, [self::PERCENT, self::FIXED], true)) {
            throw new InvalidArgumentException("Unknown mode [{$mode}].");
        }

        $roundTo = max(1, $roundTo);

        return collect($products)->map(function (Product $product) use ($mode, $value, $roundTo): array {
            $raw = $mode === self::PERCENT
                ? $product->price_minor * (1 + $value / 100)
                : $product->price_minor + $value;

            $to = (int) max(0, round($raw / $roundTo) * $roundTo);

            return ['product' => $product, 'from' => $product->price_minor, 'to' => $to];
        })->values();
    }

    /**
     * @param  Collection<int, array{product: Product, from: int, to: int}>  $preview
     */
    public function apply(Collection $preview, ?User $actor = null): int
    {
        $changed = $preview->filter(fn (array $row): bool => $row['from'] !== $row['to']);

        if ($changed->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($changed): void {
            foreach ($changed as $row) {
                // Quiet save: one cache bump and one audit entry for the whole batch.
                $row['product']->forceFill(['price_minor' => $row['to']])->saveQuietly();
            }
        });

        $restaurantId = $changed->first()['product']->restaurant_id;
        MenuCache::bump($restaurantId);

        activity()->causedBy($actor)->event('menu.bulk_price')
            ->withProperties(['changes' => $changed->map(fn (array $row): array => ['id' => $row['product']->id, 'from' => $row['from'], 'to' => $row['to']])->values()->all()])
            ->log('menu.bulk_price');

        return $changed->count();
    }
}
