<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\AnnouncementAudience;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A platform message shown at the top of every restaurant workspace
 * (new features, maintenance windows...). People can dismiss it.
 *
 * @property int $id
 * @property string $title
 * @property ?string $body
 * @property AnnouncementLevel $level
 * @property AnnouncementAudience $audience
 * @property ?string $action_label
 * @property ?string $action_url
 * @property ?CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 * @property bool $is_active
 */
#[Fillable(['title', 'body', 'level', 'audience', 'action_label', 'action_url', 'starts_at', 'ends_at', 'is_active', 'created_by'])]
#[UseFactory(AnnouncementFactory::class)]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['title', 'body', 'action_label'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'body' => null,
        'level' => 'info',
        'audience' => 'everyone',
        'action_label' => null,
        'action_url' => null,
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
        'created_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'level' => AnnouncementLevel::class,
            'audience' => AnnouncementAudience::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_dismissals')->withPivot('dismissed_at');
    }

    /**
     * Live now: active and inside its optional time window.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * Live announcements this person should see in this restaurant, minus dismissed ones.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user, Restaurant $restaurant): Builder
    {
        $isOwner = $user->employmentAt($restaurant)?->is_owner === true;

        return $query->live()
            ->whereIn('audience', $isOwner
                ? [AnnouncementAudience::Everyone->value, AnnouncementAudience::Owners->value]
                : [AnnouncementAudience::Everyone->value])
            ->whereDoesntHave('dismissedBy', fn (Builder $q) => $q->whereKey($user->getKey()))
            ->latest();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
