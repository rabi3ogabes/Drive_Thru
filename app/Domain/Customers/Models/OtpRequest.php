<?php

declare(strict_types=1);

namespace App\Domain\Customers\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A sign-in code sent to a phone. Only an HMAC of the code is stored; it
 * expires after 5 minutes and allows 5 wrong attempts.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $phone
 * @property string $code_hash
 * @property string $channel
 * @property int $attempts
 * @property ?string $ip
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $consumed_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['restaurant_id', 'phone', 'code_hash', 'channel', 'attempts', 'ip', 'expires_at', 'consumed_at'])]
#[Hidden(['code_hash'])]
class OtpRequest extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'attempts' => 0,
        'ip' => null,
        'consumed_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture() && $this->attempts < 5;
    }
}
