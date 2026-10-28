<?php

declare(strict_types=1);

namespace App\Domain\Menu\Models;

use App\Domain\Menu\Concerns\BustsMenuCache;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * When part of the menu is served (Breakfast 06:00–11:00, Late night
 * 22:00–03:00). A window that ends at or before it starts runs past
 * midnight; the hours after midnight belong to the previous day's window.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property list<int> $weekdays
 * @property string $starts_at HH:MM
 * @property string $ends_at HH:MM
 * @property bool $is_active
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'name', 'weekdays', 'starts_at', 'ends_at', 'is_active'])]
class MenuSchedule extends Model
{
    use BelongsToRestaurant, BustsMenuCache, HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** MySQL and MariaDB read TIME back as HH:MM:SS; availability compares HH:MM. */
    protected function getStartsAtAttribute(?string $value): string
    {
        return substr((string) $value, 0, 5);
    }

    protected function getEndsAtAttribute(?string $value): string
    {
        return substr((string) $value, 0, 5);
    }

    protected function setStartsAtAttribute(string $value): void
    {
        $this->attributes['starts_at'] = substr($value, 0, 5);
    }

    protected function setEndsAtAttribute(string $value): void
    {
        $this->attributes['ends_at'] = substr($value, 0, 5);
    }

    /**
     * @param  CarbonInterface  $local  a moment in the restaurant's timezone
     */
    public function isActiveAt(CarbonInterface $local): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $time = $local->format('H:i');
        $days = array_map('intval', $this->weekdays);
        $overnight = $this->ends_at <= $this->starts_at;

        if (! $overnight) {
            return in_array($local->dayOfWeek, $days, true) && $time >= $this->starts_at && $time < $this->ends_at;
        }

        // Tonight's part, or the after-midnight tail of yesterday's window.
        return (in_array($local->dayOfWeek, $days, true) && $time >= $this->starts_at)
            || (in_array($local->copy()->subDay()->dayOfWeek, $days, true) && $time < $this->ends_at);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
