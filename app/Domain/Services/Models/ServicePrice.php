<?php

declare(strict_types=1);

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\Service;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The platform's price for a service: per branch, per month, with the
 * length of its free demo. Platform-level, so not tied to a restaurant.
 *
 * @property int $id
 * @property Service $service
 * @property int $price_minor
 * @property string $currency
 * @property int $demo_days
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['service', 'price_minor', 'currency', 'demo_days', 'is_active', 'sort'])]
class ServicePrice extends Model
{
    use LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'price_minor' => 0,
        'currency' => 'QAR',
        'demo_days' => 14,
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'service' => Service::class,
            'price_minor' => 'integer',
            'demo_days' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
