<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scan of a tracked QR code. The device is a salted hash of the IP and
 * browser, never the raw values; visited_at is set when that visit reached
 * the menu.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $qr_campaign_id
 * @property string $device_hash
 * @property bool $is_bot
 * @property CarbonImmutable $scanned_at
 * @property ?CarbonImmutable $visited_at
 */
#[Fillable(['restaurant_id', 'qr_campaign_id', 'device_hash', 'is_bot', 'scanned_at'])]
class QrScan extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_bot' => false,
        'visited_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'qr_campaign_id' => 'integer',
            'is_bot' => 'boolean',
            'scanned_at' => 'immutable_datetime',
            'visited_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<QrCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(QrCampaign::class, 'qr_campaign_id');
    }
}
