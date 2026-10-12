<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Branches\Enums\StationKind;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on an order, frozen at the moment of ordering: names in both
 * languages, unit price and every chosen option with its price, so later
 * menu changes never rewrite history.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $order_id
 * @property ?int $product_id
 * @property ?StationKind $station
 * @property ?CarbonImmutable $done_at
 * @property array{en?: string, ar?: string} $name
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $options_minor
 * @property int $line_total_minor
 * @property ?list<array{kind: string, group_id: int, option_id: int, group: array<string, string>, option: array<string, string>, price_minor: int}> $options
 * @property ?string $notes
 */
#[Fillable(['restaurant_id', 'order_id', 'product_id', 'name', 'quantity', 'unit_price_minor', 'options_minor', 'line_total_minor', 'options', 'notes', 'station', 'done_at'])]
class OrderItem extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'product_id' => null,
        'options_minor' => 0,
        'options' => null,
        'notes' => null,
        'station' => null,
        'done_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'options' => 'array',
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'options_minor' => 'integer',
            'line_total_minor' => 'integer',
            'station' => StationKind::class,
            'done_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function localName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name[$locale] ?? $this->name['en'] ?? (string) reset($this->name);
    }

    /**
     * Option names in the given language, e.g. ["Large", "No onions"].
     *
     * @return list<string>
     */
    public function optionLabels(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return array_map(
            fn (array $option): string => $option['option'][$locale] ?? $option['option']['en'] ?? '',
            $this->options ?? [],
        );
    }
}
