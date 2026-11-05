<?php

declare(strict_types=1);

namespace App\Domain\Feedback\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The guest's word on a picked-up order: 1-5 stars, quick tags that fit the
 * score (see RatingTags) and an optional comment. Written once, by
 * Feedback\Actions\RateOrder only.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property int $order_id
 * @property int $customer_id
 * @property int $stars
 * @property list<string> $tags
 * @property ?string $comment
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 * @property-read Customer $customer
 * @property-read Branch $branch
 */
#[Fillable(['restaurant_id', 'branch_id', 'order_id', 'customer_id', 'stars', 'tags', 'comment', 'created_at'])]
class OrderRating extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'tags' => '[]',
        'comment' => null,
    ];

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'tags' => 'array',
            'created_at' => 'immutable_datetime',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isLow(): bool
    {
        return $this->stars <= 2;
    }
}
