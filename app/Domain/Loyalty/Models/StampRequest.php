<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Add my stamp": a signed-in guest at the counter asks, and it waits on the
 * branch's stamp screen until a waiter approves (adding the stamps) or
 * declines it. It lapses after a few minutes.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property int $customer_id
 * @property string $status pending | approved | declined | expired | cancelled
 * @property int $added
 * @property ?int $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $created_at
 * @property-read Customer $customer
 * @property-read Branch $branch
 * @property-read ?User $decider
 */
#[Fillable(['restaurant_id', 'branch_id', 'customer_id', 'expires_at'])]
class StampRequest extends Model
{
    use BelongsToRestaurant;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /** How long a request waits for the waiter. */
    public const MINUTES = 5;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::PENDING,
        'added' => 0,
        'decided_by' => null,
        'decided_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'customer_id' => 'integer',
            'added' => 'integer',
            'decided_by' => 'integer',
            'decided_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function isWaiting(): bool
    {
        return $this->status === self::PENDING && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<StampRequest>  $query
     * @return Builder<StampRequest>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::PENDING)->where('expires_at', '>', now());
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
