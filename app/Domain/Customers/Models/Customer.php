<?php

declare(strict_types=1);

namespace App\Domain\Customers\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A guest of one restaurant, known by phone number. The same phone at
 * another restaurant is a different customer: no data is shared.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $phone E.164, e.g. +97455551234
 * @property ?string $name
 * @property ?CarbonImmutable $birthday
 * @property ?string $locale
 * @property ?int $last_branch_id
 * @property int $orders_count
 * @property int $total_spent_minor
 * @property ?CarbonImmutable $last_seen_at
 * @property int $points_balance kept by Loyalty\Points only
 * @property ?int $tier_id
 * @property ?CarbonImmutable $first_order_at first picked-up order
 * @property ?CarbonImmutable $last_order_at last picked-up order
 * @property int $picked_up_count
 * @property int $orders_90d
 * @property int $spent_90d_minor
 * @property int $avg_order_minor
 * @property ?CarbonImmutable $offers_consent_at set while the guest agrees to receive offers
 * @property ?string $offers_consent_source
 * @property ?string $offers_consent_version
 * @property ?CarbonImmutable $offers_withdrawn_at
 * @property-read ?Tier $tier
 * @property ?CarbonImmutable $created_at
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'phone', 'name', 'birthday', 'locale', 'last_branch_id', 'orders_count', 'total_spent_minor', 'last_seen_at', 'notification_prefs'])]
class Customer extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'name' => null,
        'birthday' => null,
        'locale' => null,
        'notification_prefs' => null,
        'last_branch_id' => null,
        'orders_count' => 0,
        'total_spent_minor' => 0,
        'last_seen_at' => null,
        'points_balance' => 0,
        'tier_id' => null,
        'first_order_at' => null,
        'last_order_at' => null,
        'picked_up_count' => 0,
        'orders_90d' => 0,
        'spent_90d_minor' => 0,
        'avg_order_minor' => 0,
        'offers_consent_at' => null,
        'offers_consent_source' => null,
        'offers_consent_version' => null,
        'offers_withdrawn_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'immutable_date',
            'orders_count' => 'integer',
            'total_spent_minor' => 'integer',
            'points_balance' => 'integer',
            'last_seen_at' => 'immutable_datetime',
            'notification_prefs' => 'array',
            'created_at' => 'immutable_datetime',
            'first_order_at' => 'immutable_datetime',
            'last_order_at' => 'immutable_datetime',
            'picked_up_count' => 'integer',
            'orders_90d' => 'integer',
            'spent_90d_minor' => 'integer',
            'avg_order_minor' => 'integer',
            'offers_consent_at' => 'immutable_datetime',
            'offers_withdrawn_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<CustomerVehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class)->orderByDesc('is_default')->orderBy('id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    /**
     * @return HasMany<CustomerSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CustomerSession::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'customer_favorites')
            ->withPivot('restaurant_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Tier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->latest('id');
    }

    /**
     * @return HasMany<WalletReward, $this>
     */
    public function wallet(): HasMany
    {
        return $this->hasMany(WalletReward::class);
    }

    /**
     * @return HasMany<CustomerSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(CustomerSegment::class);
    }

    /** Marketing goes only to guests who said yes and haven't taken it back. */
    public function acceptsOffers(): bool
    {
        return $this->offers_consent_at !== null && $this->wants('offers');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function lastBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'last_branch_id');
    }

    public function firstName(): ?string
    {
        return $this->name !== null ? Str::of($this->name)->explode(' ')->first() : null;
    }

    /** "+974 •••• 1234" for screens where the full number isn't needed. */
    public function maskedPhone(): string
    {
        return substr($this->phone, 0, 4).' •••• '.substr($this->phone, -4);
    }

    /**
     * What the guest wants to hear about. Order updates and receipts are on
     * unless they turned them off; offers (Phase 7) are off unless turned on.
     */
    public function wants(string $topic): bool
    {
        $defaults = ['order_updates' => true, 'receipts' => true, 'offers' => false];
        $prefs = is_array($this->notification_prefs) ? $this->notification_prefs : [];

        return (bool) ($prefs[$topic] ?? $defaults[$topic] ?? false);
    }
}
