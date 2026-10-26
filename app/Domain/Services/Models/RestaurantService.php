<?php

declare(strict_types=1);

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\Service;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One service a restaurant has (or had). The price is locked when it is
 * added, so later price changes don't surprise existing clients.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property Service $service
 * @property ServiceStatus $status
 * @property int $unit_price_minor per branch per month
 * @property ?CarbonImmutable $demo_ends_at
 * @property bool $keep_after_demo
 * @property ?CarbonImmutable $demo_used_at
 * @property ?CarbonImmutable $demo_reminded_at when the owner was told the demo ends soon
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $ends_at when a cancelling service stops
 * @property ?int $added_by
 * @property-read ?User $addedBy
 */
#[Fillable(['restaurant_id', 'service', 'status', 'unit_price_minor', 'demo_ends_at', 'keep_after_demo', 'demo_used_at', 'demo_reminded_at', 'started_at', 'ends_at', 'added_by'])]
class RestaurantService extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'unit_price_minor' => 0,
        'demo_ends_at' => null,
        'keep_after_demo' => false,
        'demo_used_at' => null,
        'demo_reminded_at' => null,
        'started_at' => null,
        'ends_at' => null,
        'added_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'service' => Service::class,
            'status' => ServiceStatus::class,
            'unit_price_minor' => 'integer',
            'demo_ends_at' => 'immutable_datetime',
            'keep_after_demo' => 'boolean',
            'demo_used_at' => 'immutable_datetime',
            'demo_reminded_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function isOn(): bool
    {
        return $this->status->isOn();
    }

    public function demoDaysLeft(): ?int
    {
        if ($this->status !== ServiceStatus::Demo || $this->demo_ends_at === null) {
            return null;
        }

        return max(0, (int) ceil(CarbonImmutable::now()->diffInHours($this->demo_ends_at, false) / 24));
    }
}
