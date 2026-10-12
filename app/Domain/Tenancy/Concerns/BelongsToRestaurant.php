<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Exceptions\TenancyViolation;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\Scopes\RestaurantScope;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Required on every model that owns a restaurant_id column (an architecture
 * test enforces it). It scopes queries to the current tenant, fills the
 * restaurant on create, and forbids moving a record to another restaurant.
 *
 * @mixin Model
 */
trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope(new RestaurantScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('restaurant_id') !== null) {
                return;
            }

            $restaurantId = app(TenantContext::class)->id();

            if ($restaurantId === null) {
                throw TenancyViolation::missingTenant($model);
            }

            $model->setAttribute('restaurant_id', $restaurantId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('restaurant_id') && $model->getOriginal('restaurant_id') !== null) {
                throw TenancyViolation::crossTenantMove($model);
            }
        });
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Query across all restaurants. Only for platform-level code that has
     * already authorized the caller.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope(RestaurantScope::class);
    }
}
