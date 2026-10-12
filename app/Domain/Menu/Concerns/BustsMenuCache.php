<?php

declare(strict_types=1);

namespace App\Domain\Menu\Concerns;

use App\Domain\Menu\MenuCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Any saved or deleted menu record invalidates its restaurant's cached menus.
 *
 * @mixin Model
 */
trait BustsMenuCache
{
    public static function bootBustsMenuCache(): void
    {
        $bust = function (Model $model): void {
            $restaurantId = $model->getAttribute('restaurant_id');

            if (is_numeric($restaurantId)) {
                MenuCache::bump((int) $restaurantId);
            }
        };

        static::saved($bust);
        static::deleted($bust);
    }
}
