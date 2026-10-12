<?php

declare(strict_types=1);

namespace App\Domain\Branches\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A holiday or special closure: the branch is closed all day from starts_on
 * to ends_on (inclusive), in the restaurant's timezone.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property ?string $reason
 */
#[Fillable(['restaurant_id', 'branch_id', 'starts_on', 'ends_on', 'reason'])]
class BranchClosure extends Model
{
    use BelongsToRestaurant, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['reason'];

    /** @var array<string, mixed> */
    protected $attributes = ['reason' => null];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function covers(string $date): bool
    {
        return $this->starts_on->toDateString() <= $date && $this->ends_on->toDateString() >= $date;
    }
}
