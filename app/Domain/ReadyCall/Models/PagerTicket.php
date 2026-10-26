<?php

declare(strict_types=1);

namespace App\Domain\ReadyCall\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\Models\Order;
use App\Domain\ReadyCall\Enums\PagerStatus;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One guest waiting at the counter. The token in the guest's link is the
 * only key to this ticket, and it opens nothing else; the ready board shows
 * numbers only, never phones.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property int $number
 * @property ?string $label
 * @property PagerStatus $status
 * @property ?string $phone E.164
 * @property string $locale
 * @property string $token
 * @property ?int $order_id
 * @property int $calls times the guest was called (ready + call again)
 * @property ?CarbonImmutable $ready_at
 * @property ?CarbonImmutable $last_called_at
 * @property ?CarbonImmutable $collected_at
 * @property ?CarbonImmutable $opened_at
 * @property CarbonImmutable $expires_at
 * @property ?int $created_by
 * @property ?CarbonImmutable $created_at
 * @property-read Branch $branch
 */
#[Fillable(['restaurant_id', 'branch_id', 'number', 'label', 'status', 'phone', 'locale', 'token', 'order_id', 'calls', 'ready_at', 'last_called_at', 'collected_at', 'opened_at', 'expires_at', 'created_by'])]
class PagerTicket extends Model
{
    use BelongsToRestaurant;

    /** Hours a ticket stays usable. */
    public const LIFETIME_HOURS = 6;

    /** @var array<string, mixed> */
    protected $attributes = [
        'label' => null,
        'status' => 'waiting',
        'phone' => null,
        'locale' => 'ar',
        'order_id' => null,
        'calls' => 0,
        'ready_at' => null,
        'last_called_at' => null,
        'collected_at' => null,
        'opened_at' => null,
        'created_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => PagerStatus::class,
            'calls' => 'integer',
            'ready_at' => 'immutable_datetime',
            'last_called_at' => 'immutable_datetime',
            'collected_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutTenancy();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutTenancy();
    }

    /** Still waiting or ready, and not past its lifetime. */
    public function isLive(): bool
    {
        return $this->status->isOpen() && $this->expires_at->isFuture();
    }

    public function maskedPhone(): ?string
    {
        return $this->phone === null ? null : substr($this->phone, 0, 4).' •••• '.substr($this->phone, -2);
    }

    public static function findLive(string $token): ?self
    {
        if (preg_match('/^[A-Za-z0-9]{32}$/', $token) !== 1) {
            return null;
        }

        return self::withoutTenancy()->with('branch.restaurant')->where('token', $token)->first();
    }
}
