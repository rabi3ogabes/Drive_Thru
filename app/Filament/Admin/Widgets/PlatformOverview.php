<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Leads\LeadResource;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use App\Models\User;
use App\Support\Digits;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The business pulse of the platform: live clients, recurring revenue,
 * money waiting to be collected and the sales pipeline.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Cheap aggregate queries: render immediately instead of flashing an empty card.
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformPermission(PlatformPermission::ViewClients);
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = (string) app(PlatformSettings::class)->get('billing.currency', 'QAR');

        $byStatus = Restaurant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $trials = (int) ($byStatus[RestaurantStatus::Trial->value] ?? 0);
        $live = (int) ($byStatus[RestaurantStatus::Active->value] ?? 0) + $trials;

        $stats = [
            Stat::make(__('dashboard.admin.stats.clients'), Digits::number($live))
                ->description(trans_choice('dashboard.admin.stats.clients_trials', $trials, ['count' => Digits::number($trials)]))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary')
                ->url(RestaurantResource::getUrl()),
        ];

        if ($user->hasPlatformPermission(PlatformPermission::ManageBilling)) {
            $mrr = Subscription::withoutTenancy()
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
                ->where('currency', $currency)
                ->get()
                ->sum(fn (Subscription $s): int => $s->mrrMinor());

            $stats[] = Stat::make(__('dashboard.admin.stats.mrr'), Money::format((int) $mrr, $currency))
                ->description(__('dashboard.admin.stats.mrr_hint'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success');

            $overdue = Invoice::withoutTenancy()->where('status', InvoiceStatus::Open)->where('due_at', '<', now());
            $overdueCount = (clone $overdue)->count();
            $overdueTotal = (int) (clone $overdue)->where('currency', $currency)->sum('total_minor');

            $stats[] = Stat::make(__('dashboard.admin.stats.overdue'), Money::format($overdueTotal, $currency))
                ->description(trans_choice('dashboard.admin.stats.overdue_hint', $overdueCount, ['count' => Digits::number($overdueCount)]))
                ->descriptionIcon($overdueCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdueCount > 0 ? 'danger' : 'gray')
                ->url(InvoiceResource::getUrl());
        }

        if ($user->hasPlatformPermission(PlatformPermission::ManageLeads)) {
            $open = Lead::query()->open()->count();
            $new = Lead::query()->where('status', LeadStatus::New)->count();

            $stats[] = Stat::make(__('dashboard.admin.stats.leads'), Digits::number($open))
                ->description(trans_choice('dashboard.admin.stats.leads_new', $new, ['count' => Digits::number($new)]))
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($new > 0 ? 'warning' : 'gray')
                ->url(LeadResource::getUrl());
        }

        return $stats;
    }

    protected function getColumns(): int|array|null
    {
        return ['default' => 2, 'xl' => 4];
    }
}
