<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tracked QR code: printed somewhere (a car-window sticker, a flyer, a
 * table), opening a branch menu or the restaurant page, and counting the
 * scans, visits and orders it brings.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property ?int $branch_id
 * @property string $name
 * @property ?string $placement
 * @property ?string $lane
 * @property ?string $promo
 * @property string $code
 * @property ?CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 * @property bool $is_active
 * @property ?int $created_by
 * @property ?CarbonImmutable $created_at
 * @property-read ?Branch $branch
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'branch_id', 'name', 'placement', 'lane', 'promo', 'starts_at', 'ends_at', 'is_active', 'created_by'])]
class QrCampaign extends Model
{
    use BelongsToRestaurant;

    /** Unambiguous characters (no 0/O, 1/I/L), like order short codes. */
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** @var array<string, mixed> */
    protected $attributes = [
        'branch_id' => null,
        'placement' => null,
        'lane' => null,
        'promo' => null,
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
        'created_by' => null,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (blank($campaign->getAttribute('code'))) {
                do {
                    $code = '';
                    for ($i = 0; $i < 6; $i++) {
                        $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                    }
                } while (self::withoutTenancy()->where('code', $code)->exists());

                $campaign->setAttribute('code', $code);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** Switched on, and within its dates (if it has any). */
    public function isLive(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gt($now));
    }

    public function url(): string
    {
        return route('qr.scan', $this->code);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<QrScan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }
}
