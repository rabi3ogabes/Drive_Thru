<?php

declare(strict_types=1);

namespace App\Domain\Customers\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One device a guest is signed in on. The browser holds a random token in
 * its session; only the hash is stored, and revoking signs that device out.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property string $token_hash
 * @property ?string $ip
 * @property ?string $user_agent
 * @property ?CarbonImmutable $last_active_at
 * @property ?CarbonImmutable $revoked_at
 * @property ?CarbonImmutable $created_at
 * @property-read Customer $customer
 */
#[Fillable(['restaurant_id', 'customer_id', 'token_hash', 'ip', 'user_agent', 'last_active_at', 'revoked_at'])]
class CustomerSession extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'ip' => null,
        'user_agent' => null,
        'last_active_at' => null,
        'revoked_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** "iPhone · Safari" from the user agent, for the devices list. */
    public function deviceLabel(): string
    {
        $ua = (string) $this->user_agent;

        $device = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows',
            default => __('customers.devices.device'),
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            str_contains($ua, 'Firefox/') => 'Firefox',
            default => null,
        };

        return $browser !== null ? "{$device} · {$browser}" : $device;
    }
}
