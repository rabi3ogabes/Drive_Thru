<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * "Live data connected": the platform's live order activity, updated over
 * the platform channel the moment any restaurant's order changes.
 */
class LiveActivity extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.live-activity';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(ChannelAccess::class)->platformDashboard($user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $since = now()->subDay();

        return [
            'ordersToday' => Order::withoutTenancy()->where('placed_at', '>=', $since)->whereNot('status', OrderStatus::Cancelled->value)->count(),
            'inProgress' => Order::withoutTenancy()->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value, OrderStatus::Ready->value])->count(),
            'restaurants' => Order::withoutTenancy()->where('placed_at', '>=', $since)->distinct()->count('restaurant_id'),
            'last' => Order::withoutTenancy()->latest('placed_at')->value('placed_at'),
        ];
    }
}
