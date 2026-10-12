<?php

declare(strict_types=1);

namespace App\Domain\Branches\Models;

use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A shared screen (kitchen tablet, cashier, pickup board, TV) paired to one
 * branch. It signs in once with a one-time code and then holds a long-lived
 * token; only SHA-256 hashes of the code and token are stored.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property ?int $station_id
 * @property string $name
 * @property DeviceKind $kind
 * @property ?string $pairing_code_hash
 * @property ?CarbonImmutable $pairing_expires_at
 * @property ?string $token_hash
 * @property ?CarbonImmutable $paired_at
 * @property ?CarbonImmutable $last_seen_at
 * @property ?string $last_ip
 * @property ?string $user_agent
 * @property ?CarbonImmutable $revoked_at
 * @property ?int $created_by
 * @property-read Branch $branch
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'branch_id', 'station_id', 'name', 'kind', 'created_by'])]
#[Hidden(['pairing_code_hash', 'token_hash'])]
class Device extends Model
{
    use BelongsToRestaurant, LogsActivity;

    /** A screen that hasn't checked in for this long is shown as offline. */
    public const ONLINE_WINDOW_SECONDS = 90;

    /** @var array<string, mixed> */
    protected $attributes = [
        'station_id' => null,
        'pairing_code_hash' => null,
        'pairing_expires_at' => null,
        'token_hash' => null,
        'paired_at' => null,
        'last_seen_at' => null,
        'last_ip' => null,
        'user_agent' => null,
        'revoked_at' => null,
        'created_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'kind' => DeviceKind::class,
            'pairing_expires_at' => 'immutable_datetime',
            'paired_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
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
     * @return BelongsTo<Station, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isPaired(): bool
    {
        return $this->token_hash !== null && ! $this->isRevoked();
    }

    public function isAwaitingPairing(): bool
    {
        return ! $this->isRevoked()
            && $this->pairing_code_hash !== null
            && $this->pairing_expires_at?->isFuture() === true;
    }

    public function isOnline(): bool
    {
        return $this->isPaired()
            && $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'kind', 'branch_id', 'station_id', 'paired_at', 'revoked_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
