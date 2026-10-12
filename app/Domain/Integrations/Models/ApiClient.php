<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Integrations\Api\ApiScope;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/**
 * Another system (a POS, a kiosk, a reporting tool) allowed to use the
 * public API for one restaurant, with the scopes it was given. Its key is a
 * Sanctum token, shown once; revoking the client stops it at once.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property list<string> $scopes
 * @property ?int $created_by
 * @property ?CarbonImmutable $last_used_at
 * @property ?CarbonImmutable $revoked_at
 * @property ?CarbonImmutable $created_at
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'name', 'scopes', 'created_by', 'last_used_at', 'revoked_at'])]
class ApiClient extends Model
{
    use BelongsToRestaurant, HasApiTokens;

    /** @var array<string, mixed> */
    protected $attributes = [
        'created_by' => null,
        'last_used_at' => null,
        'revoked_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function allows(ApiScope $scope): bool
    {
        return $this->isActive() && in_array($scope->value, $this->scopes, true);
    }
}
