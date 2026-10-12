<?php

declare(strict_types=1);

namespace App\Domain\Security\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read model over Laravel's database sessions table, for the Security center.
 *
 * @property string $id
 * @property ?int $user_id
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property int $last_activity
 */
#[Table('sessions')]
#[WithoutTimestamps]
class SessionRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $hidden = ['payload'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastActiveAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->last_activity);
    }
}
