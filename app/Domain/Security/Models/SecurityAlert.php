<?php

declare(strict_types=1);

namespace App\Domain\Security\Models;

use App\Domain\Security\Enums\SecurityAlertType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property SecurityAlertType $type
 * @property string $severity
 * @property ?string $attempted_email
 * @property ?string $ip_hash
 * @property int $attempt_count
 * @property ?array<string, mixed> $details
 * @property ?Carbon $read_at
 * @property ?Carbon $created_at
 */
#[Fillable(['type', 'severity', 'attempted_email', 'ip_hash', 'attempt_count', 'details', 'read_at'])]
class SecurityAlert extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => SecurityAlertType::class,
            'details' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
