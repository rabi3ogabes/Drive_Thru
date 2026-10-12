<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How one branch serves one product: on/off, its own price, "out of stock
 * today" (until the branch's next local midnight) and a daily stock count
 * that refills itself each local day. No row means: served at the menu price.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property int $product_id
 * @property bool $is_available
 * @property ?int $price_override_minor
 * @property ?CarbonImmutable $out_of_stock_until
 * @property ?int $daily_stock
 * @property ?int $stock_remaining
 * @property ?CarbonImmutable $stock_date
 * @property ?int $low_stock_threshold
 * @property ?CarbonImmutable $low_stock_alerted_at
 * @property-read Branch $branch
 * @property-read Product $product
 */
#[Fillable(['restaurant_id', 'branch_id', 'product_id', 'is_available', 'price_override_minor', 'out_of_stock_until', 'daily_stock', 'stock_remaining', 'stock_date', 'low_stock_threshold', 'low_stock_alerted_at'])]
class BranchProduct extends Model
{
    use BelongsToRestaurant, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_available' => true,
        'price_override_minor' => null,
        'out_of_stock_until' => null,
        'daily_stock' => null,
        'stock_remaining' => null,
        'stock_date' => null,
        'low_stock_threshold' => null,
        'low_stock_alerted_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'price_override_minor' => 'integer',
            'out_of_stock_until' => 'immutable_datetime',
            'daily_stock' => 'integer',
            'stock_remaining' => 'integer',
            'stock_date' => 'immutable_date',
            'low_stock_threshold' => 'integer',
            'low_stock_alerted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_available', 'price_override_minor', 'out_of_stock_until', 'daily_stock'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
