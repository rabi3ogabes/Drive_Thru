<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Models\Order;
use Illuminate\Support\Str;

/**
 * Rebuilds cart lines from a past order ("Your usual?" / "Order again"),
 * with the same options and meal choices. The pricer re-validates them, so
 * anything no longer available is flagged rather than silently changed.
 */
final class Reorder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function lines(Order $order): array
    {
        $order->loadMissing('items');
        $lines = [];

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $options = [];
            $combo = [];

            foreach ($item->options ?? [] as $choice) {
                if (($choice['kind'] ?? 'option') === 'combo') {
                    $combo[$choice['group_id']] = $choice['option_id'];
                } else {
                    $options[$choice['group_id']][] = $choice['option_id'];
                }
            }

            $lines[] = [
                'key' => (string) Str::ulid(),
                'signature' => (string) Str::ulid(),
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'options' => $options,
                'combo' => $combo,
                'notes' => $item->notes,
            ];
        }

        return $lines;
    }
}
