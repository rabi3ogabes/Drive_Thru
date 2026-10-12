<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Ordering\Models\Payment;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money given back on a payment, full or partial, with the provider's answer.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $payment_id
 * @property int $amount_minor
 * @property string $status
 * @property ?string $reason
 * @property ?string $provider_reference
 * @property ?string $failure_reason
 * @property ?string $requested_by
 * @property-read Payment $payment
 */
#[Fillable(['restaurant_id', 'payment_id', 'amount_minor', 'status', 'reason', 'provider_reference', 'failure_reason', 'requested_by'])]
class Refund extends Model
{
    use BelongsToRestaurant;

    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::PENDING,
        'reason' => null,
        'provider_reference' => null,
        'failure_reason' => null,
        'requested_by' => null,
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
