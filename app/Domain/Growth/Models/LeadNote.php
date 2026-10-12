<?php

declare(strict_types=1);

namespace App\Domain\Growth\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property ?int $user_id
 * @property string $body
 * @property ?CarbonImmutable $created_at
 */
#[Fillable(['lead_id', 'user_id', 'body'])]
class LeadNote extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'user_id' => null,
    ];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
