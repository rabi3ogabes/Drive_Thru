<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An earn boost: "double points on burgers", "×3 at the airport branch".
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $scope product | category | branch
 * @property int $scope_id
 * @property int $multiplier_bp 20000 = ×2
 * @property ?CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 */
#[Fillable(['restaurant_id', 'scope', 'scope_id', 'multiplier_bp', 'starts_at', 'ends_at'])]
class EarnRule extends Model
{
    use BelongsToRestaurant;

    public const SCOPES = ['product', 'category', 'branch'];

    protected $table = 'loyalty_earn_rules';

    /** @var array<string, mixed> */
    protected $attributes = [
        'starts_at' => null,
        'ends_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'multiplier_bp' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function isLiveAt(CarbonInterface $at): bool
    {
        return ($this->starts_at === null || $this->starts_at->lte($at)) && ($this->ends_at === null || $this->ends_at->gt($at));
    }
}
