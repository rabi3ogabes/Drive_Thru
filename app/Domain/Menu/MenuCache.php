<?php

declare(strict_types=1);

namespace App\Domain\Menu;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned menu cache. Every menu change bumps the restaurant's version, so
 * all of its cached branch menus become unreachable at once (tag-style
 * invalidation that works on every cache store, including database/file).
 */
final class MenuCache
{
    private const TTL_SECONDS = 86_400;

    public static function version(int $restaurantId): int
    {
        return (int) Cache::rememberForever("menu:{$restaurantId}:version", fn (): int => 1);
    }

    public static function bump(int $restaurantId): void
    {
        $key = "menu:{$restaurantId}:version";

        Cache::forever($key, self::version($restaurantId) + 1);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function remember(int $restaurantId, string $name, Closure $build): mixed
    {
        $key = sprintf('menu:%d:v%d:%s', $restaurantId, self::version($restaurantId), $name);

        return Cache::remember($key, self::TTL_SECONDS, $build);
    }
}
