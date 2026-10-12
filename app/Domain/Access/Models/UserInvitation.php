<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time link that lets an invited person choose their own password.
 * Only a SHA-256 hash of the token is stored.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $invited_by
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $accepted_at
 * @property ?CarbonImmutable $revoked_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'invited_by', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at'])]
class UserInvitation extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'invited_by' => null,
        'accepted_at' => null,
        'revoked_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
