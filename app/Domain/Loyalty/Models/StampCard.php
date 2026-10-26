<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One guest's card for one stamp program. Stamps only change through
 * Loyalty\Stamps, under a lock on this row.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property int $stamp_program_id
 * @property int $stamps
 * @property int $completed_count
 * @property ?CarbonImmutable $last_stamped_at
 * @property ?CarbonImmutable $expires_at
 * @property-read StampProgram $program
 * @property-read Customer $customer
 */
#[Fillable(['restaurant_id', 'customer_id', 'stamp_program_id', 'stamps', 'completed_count', 'last_stamped_at', 'expires_at'])]
class StampCard extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'stamps' => 0,
        'completed_count' => 0,
        'last_stamped_at' => null,
        'expires_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'stamps' => 'integer',
            'completed_count' => 'integer',
            'last_stamped_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<StampProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(StampProgram::class, 'stamp_program_id')->withoutTenancy();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutTenancy();
    }

    /**
     * @return HasMany<StampEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(StampEvent::class)->latest('id');
    }
}
