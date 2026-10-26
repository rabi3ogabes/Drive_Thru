<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Crm\Enums\Segment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest's membership of a segment, as of the last nightly run.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property Segment $segment
 * @property int $product_id 0 unless the segment is "frequent buyer of"
 * @property CarbonImmutable $computed_at
 * @property-read ?Product $product
 */
#[Fillable(['restaurant_id', 'customer_id', 'segment', 'product_id', 'computed_at'])]
class CustomerSegment extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'product_id' => 0,
    ];

    protected function casts(): array
    {
        return [
            'segment' => Segment::class,
            'product_id' => 'integer',
            'computed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
