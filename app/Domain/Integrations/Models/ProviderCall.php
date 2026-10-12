<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One call to an outside provider: what, how long, and whether it worked.
 * Feeds the platform health page and failure alerts; kept for 30 days.
 *
 * @property int $id
 * @property ?int $restaurant_id
 * @property string $provider
 * @property string $operation
 * @property bool $ok
 * @property ?int $http_status
 * @property int $duration_ms
 * @property ?string $error
 * @property CarbonImmutable $created_at
 */
#[Fillable(['restaurant_id', 'provider', 'operation', 'ok', 'http_status', 'duration_ms', 'error', 'created_at'])]
class ProviderCall extends Model
{
    use BelongsToRestaurant, MassPrunable;

    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'http_status' => null,
        'duration_ms' => 0,
        'error' => null,
    ];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::withoutTenancy()->where('created_at', '<', now()->subDays(30));
    }
}
