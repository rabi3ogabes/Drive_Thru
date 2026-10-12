<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of a guest's points. Credits keep how many of their points
 * are still unspent (`remaining`), so spending and expiry work lot by lot.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property ?int $order_id
 * @property LedgerType $type
 * @property int $points positive = credit, negative = debit
 * @property int $remaining
 * @property ?CarbonImmutable $expires_at
 * @property ?string $note
 * @property ?int $created_by
 * @property CarbonImmutable $created_at
 * @property-read ?Order $order
 */
#[Fillable(['restaurant_id', 'customer_id', 'order_id', 'type', 'points', 'remaining', 'expires_at', 'note', 'created_by', 'created_at'])]
class LedgerEntry extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    protected $table = 'loyalty_ledger';

    /** @var array<string, mixed> */
    protected $attributes = [
        'order_id' => null,
        'remaining' => 0,
        'expires_at' => null,
        'note' => null,
        'created_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'points' => 'integer',
            'remaining' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
