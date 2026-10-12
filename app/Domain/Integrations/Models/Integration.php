<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A restaurant's own account with a provider (payments, printing, POS).
 * Credentials are encrypted at rest and never shown again after saving.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property IntegrationKind $kind
 * @property string $provider
 * @property string $mode
 * @property ?array<string, string> $credentials
 * @property ?array<string, mixed> $options
 * @property bool $is_enabled
 * @property ?CarbonImmutable $verified_at
 * @property ?string $last_error
 */
#[Fillable(['restaurant_id', 'kind', 'provider', 'mode', 'credentials', 'options', 'is_enabled', 'verified_at', 'last_error'])]
class Integration extends Model
{
    use BelongsToRestaurant;

    public const TEST = 'test';

    public const LIVE = 'live';

    /** @var array<string, mixed> */
    protected $attributes = [
        'mode' => self::TEST,
        'credentials' => null,
        'options' => null,
        'is_enabled' => false,
        'verified_at' => null,
        'last_error' => null,
    ];

    /** Credentials never leave the server in arrays or JSON. */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'kind' => IntegrationKind::class,
            'credentials' => 'encrypted:array',
            'options' => 'array',
            'is_enabled' => 'boolean',
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function isLive(): bool
    {
        return $this->mode === self::LIVE;
    }
}
