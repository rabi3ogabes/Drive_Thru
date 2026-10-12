<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * The guest's cart for one restaurant, kept in their session. It holds ids
 * and choices only; prices always come from CartPricer.
 */
final class Cart
{
    public function __construct(private readonly Session $session) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(int $restaurantId): array
    {
        $lines = $this->session->get($this->key($restaurantId), []);

        return is_array($lines) ? array_values($lines) : [];
    }

    /**
     * Adds a line, merging with an identical one (same item, options, meal choices and notes).
     *
     * @param  array<int|string, mixed>  $options
     * @param  array<int|string, mixed>  $combo
     */
    public function add(int $restaurantId, int $productId, int $quantity = 1, array $options = [], array $combo = [], ?string $notes = null): string
    {
        $lines = $this->lines($restaurantId);
        $signature = $this->signature($productId, $options, $combo, $notes);

        foreach ($lines as $i => $line) {
            if (($line['signature'] ?? null) === $signature) {
                $lines[$i]['quantity'] = min(CartPricer::MAX_QUANTITY, (int) $line['quantity'] + $quantity);
                $this->save($restaurantId, $lines);

                return (string) $line['key'];
            }
        }

        $key = (string) Str::ulid();
        $lines[] = [
            'key' => $key,
            'signature' => $signature,
            'product_id' => $productId,
            'quantity' => max(1, min(CartPricer::MAX_QUANTITY, $quantity)),
            'options' => $options,
            'combo' => $combo,
            'notes' => $notes,
        ];
        $this->save($restaurantId, $lines);

        return $key;
    }

    public function setQuantity(int $restaurantId, string $key, int $quantity): void
    {
        $lines = $this->lines($restaurantId);

        foreach ($lines as $i => $line) {
            if ($line['key'] === $key) {
                if ($quantity <= 0) {
                    unset($lines[$i]);
                } else {
                    $lines[$i]['quantity'] = min(CartPricer::MAX_QUANTITY, $quantity);
                }
            }
        }

        $this->save($restaurantId, array_values($lines));
    }

    public function remove(int $restaurantId, string $key): void
    {
        $this->setQuantity($restaurantId, $key, 0);
    }

    public function clear(int $restaurantId): void
    {
        $this->session->forget($this->key($restaurantId));
    }

    public function count(int $restaurantId): int
    {
        return array_sum(array_map(fn (array $l): int => (int) $l['quantity'], $this->lines($restaurantId)));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function replace(int $restaurantId, array $lines): void
    {
        $this->save($restaurantId, $lines);
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @param  array<int|string, mixed>  $combo
     */
    private function signature(int $productId, array $options, array $combo, ?string $notes): string
    {
        ksort($options);
        ksort($combo);
        $normalized = array_map(function (mixed $ids): array {
            $ids = array_map('intval', (array) $ids);
            sort($ids);

            return $ids;
        }, $options);

        return hash('xxh128', json_encode([$productId, $normalized, $combo, trim((string) $notes)], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function save(int $restaurantId, array $lines): void
    {
        $this->session->put($this->key($restaurantId), $lines);
    }

    private function key(int $restaurantId): string
    {
        return "cart.{$restaurantId}";
    }
}
