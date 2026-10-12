<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Lane;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A guest's order at one branch. Totals are computed on the server when the
 * order is placed and never trusted from the browser. The status is only
 * changed through `TransitionOrder`.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property int $customer_id
 * @property ?int $lane_id
 * @property int $number
 * @property string $short_code
 * @property string $pickup_code
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property PaymentMethod $payment_method
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property int $service_fee_minor
 * @property int $total_minor
 * @property OrderTiming $timing
 * @property ?int $delay_minutes
 * @property ?CarbonImmutable $scheduled_for
 * @property ?CarbonImmutable $prepare_at
 * @property ?CarbonImmutable $ready_estimate_at
 * @property int $prep_minutes
 * @property ?array<string, string|null> $vehicle
 * @property ?string $notes
 * @property ?array<string, string> $source
 * @property string $locale
 * @property string $idempotency_key
 * @property ?CarbonImmutable $placed_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?string $cancel_reason
 * @property ?CarbonImmutable $received_at
 * @property ?CarbonImmutable $preparing_at
 * @property ?CarbonImmutable $ready_at
 * @property ?CarbonImmutable $picked_up_at
 * @property ?CarbonImmutable $on_the_way_at
 * @property ?CarbonImmutable $arrived_at
 * @property ?CarbonImmutable $location_consent_at
 * @property ?CarbonImmutable $location_denied_at
 * @property ?CarbonImmutable $last_location_at
 * @property ?CarbonImmutable $approach_alerted_at
 * @property ?CarbonImmutable $delay_alerted_at
 * @property ?CarbonImmutable $wait_alerted_at
 * @property ?string $pos_reference
 * @property ?CarbonImmutable $pos_synced_at
 * @property ?string $pos_error
 * @property int $points_redeemed
 * @property int $points_earned
 * @property-read Collection<int, OrderAdjustment> $adjustments
 * @property ?string $arrival_source
 * @property ?float $last_latitude
 * @property ?float $last_longitude
 * @property ?int $last_distance_m
 * @property ?CarbonImmutable $created_at
 * @property-read Restaurant $restaurant
 * @property-read Branch $branch
 * @property-read Customer $customer
 * @property-read ?Lane $lane
 * @property-read Collection<int, OrderItem> $items
 * @property-read ?Payment $payment
 */
#[Fillable([
    'restaurant_id', 'branch_id', 'customer_id', 'lane_id', 'number', 'short_code', 'pickup_code', 'status',
    'payment_status', 'payment_method', 'currency', 'subtotal_minor', 'discount_minor', 'tax_minor',
    'service_fee_minor', 'total_minor', 'timing', 'delay_minutes', 'scheduled_for', 'prepare_at',
    'ready_estimate_at', 'prep_minutes', 'vehicle', 'notes', 'source', 'locale', 'idempotency_key',
    'placed_at', 'cancelled_at', 'cancel_reason', 'received_at', 'preparing_at', 'ready_at', 'picked_up_at',
    'on_the_way_at', 'arrived_at', 'arrival_source', 'location_consent_at', 'location_denied_at', 'last_latitude',
    'last_longitude', 'last_distance_m', 'last_location_at', 'approach_alerted_at', 'delay_alerted_at', 'wait_alerted_at',
    'pos_reference', 'pos_synced_at', 'pos_error', 'points_redeemed', 'points_earned',
])]
class Order extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'lane_id' => null,
        'payment_status' => 'unpaid',
        'discount_minor' => 0,
        'tax_minor' => 0,
        'service_fee_minor' => 0,
        'timing' => 'now',
        'delay_minutes' => null,
        'scheduled_for' => null,
        'prepare_at' => null,
        'ready_estimate_at' => null,
        'vehicle' => null,
        'notes' => null,
        'source' => null,
        'locale' => 'ar',
        'placed_at' => null,
        'cancelled_at' => null,
        'cancel_reason' => null,
        'received_at' => null,
        'preparing_at' => null,
        'ready_at' => null,
        'picked_up_at' => null,
        'on_the_way_at' => null,
        'arrived_at' => null,
        'location_consent_at' => null,
        'location_denied_at' => null,
        'last_location_at' => null,
        'approach_alerted_at' => null,
        'delay_alerted_at' => null,
        'wait_alerted_at' => null,
        'pos_reference' => null,
        'pos_synced_at' => null,
        'pos_error' => null,
        'points_redeemed' => 0,
        'points_earned' => 0,
        'arrival_source' => null,
        'last_latitude' => null,
        'last_longitude' => null,
        'last_distance_m' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'timing' => OrderTiming::class,
            'number' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'service_fee_minor' => 'integer',
            'total_minor' => 'integer',
            'delay_minutes' => 'integer',
            'prep_minutes' => 'integer',
            'scheduled_for' => 'immutable_datetime',
            'prepare_at' => 'immutable_datetime',
            'ready_estimate_at' => 'immutable_datetime',
            'placed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'preparing_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'picked_up_at' => 'immutable_datetime',
            'on_the_way_at' => 'immutable_datetime',
            'arrived_at' => 'immutable_datetime',
            'location_consent_at' => 'immutable_datetime',
            'location_denied_at' => 'immutable_datetime',
            'last_location_at' => 'immutable_datetime',
            'approach_alerted_at' => 'immutable_datetime',
            'delay_alerted_at' => 'immutable_datetime',
            'wait_alerted_at' => 'immutable_datetime',
            'pos_synced_at' => 'immutable_datetime',
            'points_redeemed' => 'integer',
            'points_earned' => 'integer',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_distance_m' => 'integer',
            'vehicle' => 'array',
            'source' => 'array',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Lane, $this>
     */
    public function lane(): BelongsTo
    {
        return $this->belongsTo(Lane::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusChange, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderBy('id');
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** "#1042" as shown to guests and staff. */
    /** The payment state as staff need it: unpaid pay-at-pickup orders are money to collect. */
    public function staffPaymentLabel(): string
    {
        if ($this->payment_method === PaymentMethod::PayAtPickup && $this->payment_status === PaymentStatus::Unpaid) {
            return __('ordering.to_collect');
        }

        return $this->payment_status->getLabel();
    }

    public function displayNumber(): string
    {
        return '#'.$this->number;
    }

    public function itemsCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function getRouteKeyName(): string
    {
        return 'short_code';
    }
}
