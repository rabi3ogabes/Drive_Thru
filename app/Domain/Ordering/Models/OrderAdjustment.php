<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A discount line on a placed order (promotion, coupon, reward or points),
 * kept as it was when the guest ordered.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $order_id
 * @property string $kind
 * @property int $source_id
 * @property array<string, string> $label
 * @property int $amount_minor
 * @property int $bonus_points
 * @property CarbonImmutable $created_at
 */
#[Fillable(['restaurant_id', 'order_id', 'kind', 'source_id', 'label', 'amount_minor', 'bonus_points', 'created_at'])]
class OrderAdjustment extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'source_id' => 0,
        'amount_minor' => 0,
        'bonus_points' => 0,
    ];

    protected function casts(): array
    {
        return [
            'label' => 'array',
            'source_id' => 'integer',
            'amount_minor' => 'integer',
            'bonus_points' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function localLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->label[$locale] ?? (string) (reset($this->label) ?: '');
    }
}
