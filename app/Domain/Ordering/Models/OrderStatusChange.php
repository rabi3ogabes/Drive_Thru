<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One step in an order's history: from → to, who did it, when.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $order_id
 * @property ?OrderStatus $from
 * @property OrderStatus $to
 * @property string $actor_type customer | staff | device | system | integration
 * @property ?int $actor_id
 * @property ?string $note
 * @property CarbonImmutable $created_at
 */
#[Table('order_status_history')]
#[Fillable(['restaurant_id', 'order_id', 'from', 'to', 'actor_type', 'actor_id', 'note', 'created_at'])]
class OrderStatusChange extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'from' => null,
        'actor_id' => null,
        'note' => null,
    ];

    protected function casts(): array
    {
        return [
            'from' => OrderStatus::class,
            'to' => OrderStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
