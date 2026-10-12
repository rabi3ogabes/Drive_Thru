<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant model to the current restaurant.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final class RestaurantScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $restaurantId = app(TenantContext::class)->id();

        if ($restaurantId !== null) {
            $builder->where($model->qualifyColumn('restaurant_id'), $restaurantId);
        }
    }
}
