<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment attempt for an order, through a gateway driver.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $order_id
 * @property PaymentMethod $method
 * @property string $gateway
 * @property PaymentStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property ?string $reference
 * @property ?string $redirect_url
 * @property ?string $failure_reason
 * @property ?array<string, mixed> $meta
 * @property ?CarbonImmutable $paid_at
 * @property ?string $provider_transaction
 * @property string $mode
 * @property ?int $fee_minor
 * @property int $refunded_minor
 * @property ?CarbonImmutable $refunded_at
 * @property ?CarbonImmutable $last_checked_at
 * @property-read Order $order
 */
#[Fillable(['restaurant_id', 'order_id', 'method', 'gateway', 'status', 'amount_minor', 'currency', 'reference', 'redirect_url', 'failure_reason', 'meta', 'paid_at', 'provider_transaction', 'mode', 'fee_minor', 'refunded_minor', 'refunded_at', 'last_checked_at'])]
class Payment extends Model
{
    use BelongsToRestaurant;

    /** Money taken at the window (cash or card terminal), not through an online provider. */
    public const COUNTER = 'counter';

    /** @var array<string, mixed> */
    protected $attributes = [
        'reference' => null,
        'redirect_url' => null,
        'failure_reason' => null,
        'meta' => null,
        'paid_at' => null,
        'provider_transaction' => null,
        'mode' => 'test',
        'fee_minor' => null,
        'refunded_minor' => 0,
        'refunded_at' => null,
        'last_checked_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'meta' => 'array',
            'paid_at' => 'immutable_datetime',
            'fee_minor' => 'integer',
            'refunded_minor' => 'integer',
            'refunded_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isOnline(): bool
    {
        return $this->gateway !== self::COUNTER;
    }

    /** Money that can still be given back. */
    public function refundableMinor(): int
    {
        return max(0, $this->amount_minor - $this->refunded_minor);
    }
}
