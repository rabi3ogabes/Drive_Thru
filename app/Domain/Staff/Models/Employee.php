<?php

declare(strict_types=1);

namespace App\Domain\Staff\Models;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\Position;
use App\Domain\Branches\Models\Branch;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A user's membership of one restaurant: their position, the branches they
 * cover, owner flag, status and shared-device PIN.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $user_id
 * @property ?int $position_id
 * @property bool $is_owner
 * @property bool $all_branches
 * @property ?string $pin_hash
 * @property EmployeeStatus $status
 * @property ?Carbon $invited_at
 * @property ?Carbon $joined_at
 * @property-read User $user
 * @property-read ?Position $position
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'user_id', 'position_id', 'is_owner', 'all_branches', 'status', 'invited_at', 'joined_at'])]
#[Hidden(['pin_hash'])]
#[UseFactory(EmployeeFactory::class)]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToRestaurant, HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'position_id' => null,
        'is_owner' => false,
        'all_branches' => false,
        'pin_hash' => null,
        'status' => 'active',
        'invited_at' => null,
        'joined_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'all_branches' => 'boolean',
            'status' => EmployeeStatus::class,
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Branches this person is assigned to. Owners and "all branches" staff
     * cover every branch regardless of this list.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    public function coversAllBranches(): bool
    {
        return $this->is_owner || $this->all_branches;
    }

    /**
     * The branch ids this person works in, or null for every branch.
     *
     * @return ?list<int>
     */
    public function branchIds(): ?array
    {
        if ($this->coversAllBranches()) {
            return null;
        }

        $this->loadMissing('branches');

        return array_values(array_map(intval(...), $this->branches->modelKeys()));
    }

    public function coversBranch(Branch|int $branch): bool
    {
        $ids = $this->branchIds();

        return $ids === null || in_array($branch instanceof Branch ? $branch->getKey() : $branch, $ids, true);
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public function allows(RestaurantPermission $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->is_owner) {
            return true;
        }

        return $this->position?->grants($permission) ?? false;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['position_id', 'is_owner', 'all_branches', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
