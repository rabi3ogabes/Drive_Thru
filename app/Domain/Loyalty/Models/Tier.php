<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A loyalty level (Bronze, Silver…). Guests reach it with the points they
 * earned in the program's window; it multiplies what they earn.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property int $threshold_points
 * @property int $earn_multiplier_bp 10000 = ×1
 * @property ?string $benefits
 * @property int $sort
 */
#[Fillable(['restaurant_id', 'name', 'threshold_points', 'earn_multiplier_bp', 'benefits', 'sort'])]
class Tier extends Model
{
    use BelongsToRestaurant, HasTranslations;

    protected $table = 'loyalty_tiers';

    /** @var list<string> */
    public array $translatable = ['name', 'benefits'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'threshold_points' => 0,
        'earn_multiplier_bp' => 10_000,
        'benefits' => null,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'threshold_points' => 'integer',
            'earn_multiplier_bp' => 'integer',
            'sort' => 'integer',
        ];
    }
}
