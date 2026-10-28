<?php

declare(strict_types=1);

namespace App\Domain\Branches\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One opening shift on a weekday. Several rows on the same day are split
 * shifts; closes_at earlier than opens_at means the shift ends after midnight.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $branch_id
 * @property int $weekday 0 = Sunday … 6 = Saturday
 * @property string $opens_at HH:MM
 * @property string $closes_at HH:MM
 */
#[Fillable(['restaurant_id', 'branch_id', 'weekday', 'opens_at', 'closes_at'])]
class BranchHour extends Model
{
    use BelongsToRestaurant;

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function crossesMidnight(): bool
    {
        return $this->closes_at <= $this->opens_at;
    }

    /** MySQL and MariaDB read TIME back as HH:MM:SS; the schedule compares HH:MM. */
    protected function getOpensAtAttribute(?string $value): string
    {
        return substr((string) $value, 0, 5);
    }

    protected function getClosesAtAttribute(?string $value): string
    {
        return substr((string) $value, 0, 5);
    }

    protected function setOpensAtAttribute(string $value): void
    {
        $this->attributes['opens_at'] = substr($value, 0, 5);
    }

    protected function setClosesAtAttribute(string $value): void
    {
        $this->attributes['closes_at'] = substr($value, 0, 5);
    }
}
