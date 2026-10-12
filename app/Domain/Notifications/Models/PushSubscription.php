<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A browser that agreed to receive push notifications: a guest's phone for
 * their order, or a staff member's device for alerts. Removed automatically
 * when the browser says the subscription has expired.
 *
 * @property int $id
 * @property string $subscriber_type
 * @property int $subscriber_id
 * @property string $endpoint
 * @property string $public_key
 * @property string $auth_token
 * @property string $locale
 * @property ?CarbonImmutable $last_used_at
 */
#[Fillable(['subscriber_type', 'subscriber_id', 'endpoint', 'public_key', 'auth_token', 'locale', 'last_used_at'])]
class PushSubscription extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'locale' => 'ar',
        'last_used_at' => null,
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }
}
