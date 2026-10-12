<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A URL in another system (a POS, a delivery hub) that receives signed
 * events. The signing secret is encrypted and shown once, when created or
 * rotated. An endpoint that keeps failing is switched off automatically.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property ?string $description
 * @property bool $is_active
 * @property ?CarbonImmutable $disabled_at
 * @property ?CarbonImmutable $created_at
 */
#[Fillable(['restaurant_id', 'url', 'secret', 'events', 'description', 'is_active', 'disabled_at'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'description' => null,
        'is_active' => true,
        'disabled_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'disabled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(WebhookEvent $event): bool
    {
        return $this->is_active && in_array($event->value, $this->events, true);
    }

    public static function newSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }
}
