<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Access\DefaultPositions;
use App\Domain\Access\Models\Position;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a restaurant with its built-in positions and, optionally, its owner
 * membership, all in one transaction.
 */
final class CreateRestaurant
{
    /**
     * @param  array{ar: string, en: string}  $name
     */
    public function handle(
        array $name,
        ?User $owner = null,
        ?string $slug = null,
        RestaurantStatus $status = RestaurantStatus::Active,
        string $currency = 'QAR',
        string $timezone = 'Asia/Qatar',
        string $defaultLocale = 'ar',
    ): Restaurant {
        return DB::transaction(function () use ($name, $owner, $slug, $status, $currency, $timezone, $defaultLocale): Restaurant {
            $restaurant = Restaurant::create([
                'name' => $name,
                'slug' => $slug ?? $this->uniqueSlug($name['en']),
                'status' => $status,
                'currency' => $currency,
                'timezone' => $timezone,
                'default_locale' => $defaultLocale,
            ]);

            $sort = 0;
            foreach (DefaultPositions::all() as $key => $permissions) {
                Position::create([
                    'restaurant_id' => $restaurant->id,
                    'key' => $key,
                    'name' => ['ar' => __("positions.{$key}.name", [], 'ar'), 'en' => __("positions.{$key}.name", [], 'en')],
                    'description' => ['ar' => __("positions.{$key}.description", [], 'ar'), 'en' => __("positions.{$key}.description", [], 'en')],
                    'permissions' => $permissions,
                    'is_system' => true,
                    'sort' => $sort++,
                ]);
            }

            if ($owner !== null) {
                Employee::create([
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $owner->id,
                    'is_owner' => true,
                    'status' => EmployeeStatus::Active,
                    'joined_at' => now(),
                ]);
            }

            return $restaurant;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'restaurant';
        $slug = $base;
        $suffix = 2;

        while (Restaurant::withTrashed()->where('slug', $slug)->exists() || in_array($slug, self::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Route pattern for a public restaurant slug: any slug except the
     * platform's own paths, so /admin/login can never resolve as a branch link.
     */
    public static function publicSlugPattern(): string
    {
        $reserved = implode('|', array_map(fn (string $slug): string => preg_quote($slug, '#'), self::reservedSlugs()));

        return '(?!(?:'.$reserved.')(?:/|$))[a-z0-9][a-z0-9-]*';
    }

    /**
     * Paths owned by the platform, so a restaurant link can never shadow them.
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return [
            'admin', 'app', 'ops', 'api', 'pair', 'screen', 'r', 'o', 'locale', 'login', 'logout', 'up', 'storage', 'build',
            'features', 'pricing', 'contact', 'demo', 'terms', 'privacy', 'cookies', 'blog', 'help',
            'livewire', 'filament', 'broadcasting', 'realtime', 'pay', 'payments', 'webhooks', 'print', 'push', 'qr', 'invoices', 'impersonation', 'developers', 'sw.js', 'offline.html',
            'sitemap.xml', 'robots.txt', 'manifest.json',
        ];
    }
}
