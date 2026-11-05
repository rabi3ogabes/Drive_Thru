<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Ordering\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A guest's "report a problem" about one picked-up order: the conversation
 * with the branch, and how it was put right (a refund or a reward).
 * Opened by Support\Actions\OpenTicket; staff act through the other actions.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property int $order_id
 * @property int $customer_id
 * @property int $number
 * @property TicketCategory $category
 * @property list<int> $items
 * @property string $status open | answered | resolved
 * @property ?string $outcome refund | reward
 * @property ?int $refund_id
 * @property ?int $wallet_reward_id
 * @property ?CarbonImmutable $first_reply_at
 * @property ?CarbonImmutable $resolved_at
 * @property ?int $resolved_by
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 * @property-read Customer $customer
 * @property-read Branch $branch
 * @property-read ?Refund $refund
 * @property-read ?WalletReward $walletReward
 * @property-read ?User $resolver
 * @property-read Collection<int, TicketMessage> $messages
 */
#[Fillable(['restaurant_id', 'branch_id', 'order_id', 'customer_id', 'number', 'category', 'items'])]
class SupportTicket extends Model
{
    use BelongsToRestaurant;

    public const OPEN = 'open';

    public const ANSWERED = 'answered';

    public const RESOLVED = 'resolved';

    public const STATUSES = [self::OPEN, self::ANSWERED, self::RESOLVED];

    /** Tickets are numbered from here in each restaurant: T-1001, T-1002… */
    public const FIRST_NUMBER = 1001;

    /** Past this, an unresolved ticket is flagged in the inbox. */
    public const OVERDUE_HOURS = 24;

    /** @var array<string, mixed> */
    protected $attributes = [
        'items' => '[]',
        'status' => self::OPEN,
        'outcome' => null,
        'refund_id' => null,
        'wallet_reward_id' => null,
        'first_reply_at' => null,
        'resolved_at' => null,
        'resolved_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'items' => 'array',
            'number' => 'integer',
            'first_reply_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function displayNumber(): string
    {
        return 'T-'.$this->number;
    }

    public function isOpen(): bool
    {
        return $this->status !== self::RESOLVED;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->created_at->lt(now()->subHours(self::OVERDUE_HOURS));
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

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return BelongsTo<WalletReward, $this>
     */
    public function walletReward(): BelongsTo
    {
        return $this->belongsTo(WalletReward::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }
}
