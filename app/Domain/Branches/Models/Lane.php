<?php

declare(strict_types=1);

namespace App\Domain\Branches\Models;

use App\Domain\Branches\Enums\LaneKind;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A drive-thru lane or curbside spot that a guest picks when they arrive.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property string $name
 * @property LaneKind $kind
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['restaurant_id', 'branch_id', 'name', 'kind', 'is_active', 'sort'])]
class Lane extends Model
{
    use BelongsToRestaurant, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => 'lane',
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => LaneKind::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
