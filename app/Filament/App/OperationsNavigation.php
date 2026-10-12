<?php

declare(strict_types=1);

namespace App\Filament\App;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Domain\Tenancy\Models\Restaurant;
use App\Http\Controllers\OpsController;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

/**
 * Sidebar links from the restaurant workspace to the live operations
 * screens, shown only to people whose position allows each screen.
 */
final class OperationsNavigation
{
    /**
     * @return list<NavigationItem>
     */
    public static function items(): array
    {
        $screens = [
            'live' => ['heroicon-o-queue-list', 1],
            'kitchen' => ['heroicon-o-fire', 2],
            'pickup' => ['heroicon-o-qr-code', 3],
            'tv' => ['heroicon-o-tv', 4],
        ];

        $items = [
            NavigationItem::make('ops-overview')
                ->label(fn (): string => __('ops.screens.overview'))
                ->icon('heroicon-o-squares-2x2')
                ->group(WorkspaceNavigationGroup::Operations->name)
                ->sort(0)
                ->url(fn (): string => route('ops.overview', self::restaurant()))
                ->visible(fn (): bool => self::restaurant() !== null && self::user() !== null && app(ChannelAccess::class)->restaurantDashboard(self::user(), self::restaurant()->id)),
        ];

        foreach ($screens as $screen => [$icon, $sort]) {
            $items[] = NavigationItem::make('ops-'.$screen)
                ->label(fn (): string => __('ops.screens.'.$screen))
                ->icon($icon)
                ->group(WorkspaceNavigationGroup::Operations->name)
                ->sort($sort)
                ->url(fn (): string => route('ops.go', [self::restaurant(), $screen]))
                ->visible(fn (): bool => self::allowed(OpsController::SCREENS[$screen][1]));
        }

        return $items;
    }

    /**
     * @param  list<P>  $permissions
     */
    private static function allowed(array $permissions): bool
    {
        $restaurant = self::restaurant();
        $user = self::user();

        if ($restaurant === null || $user === null) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasRestaurantPermission($restaurant->id, $permission)) {
                return true;
            }
        }

        return false;
    }

    private static function restaurant(): ?Restaurant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Restaurant ? $tenant : null;
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
