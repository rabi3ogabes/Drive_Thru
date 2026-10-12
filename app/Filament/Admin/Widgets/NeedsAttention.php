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
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Staff\Models\Employee;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Leads\LeadResource;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use App\Filament\Admin\Resources\SecurityAlerts\SecurityAlertResource;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * "What needs my attention?": the short list of things someone should act
 * on today, each linking straight to where it is handled.
 */
class NeedsAttention extends Widget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.admin.widgets.needs-attention';

    /**
     * @return list<array{icon: string, color: string, title: string, url: string}>
     */
    public function items(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $items = [];

        if ($user->hasPlatformPermission(PlatformPermission::ManageLeads)) {
            $waiting = Lead::query()->where('status', LeadStatus::New)->where('created_at', '<', now()->subDay())->count();
            if ($waiting > 0) {
                $items[] = ['icon' => 'heroicon-o-inbox-arrow-down', 'color' => 'danger', 'title' => trans_choice('dashboard.attention.leads_waiting', $waiting, ['count' => $waiting]), 'url' => LeadResource::getUrl()];
            }

            $followUps = Lead::query()->open()->where('follow_up_at', '<=', now())->count();
            if ($followUps > 0) {
                $items[] = ['icon' => 'heroicon-o-bell-alert', 'color' => 'warning', 'title' => trans_choice('dashboard.attention.follow_ups', $followUps, ['count' => $followUps]), 'url' => LeadResource::getUrl('index', ['tableFilters' => ['follow_up_due' => ['isActive' => true]]])];
            }
        }

        if ($user->hasPlatformPermission(PlatformPermission::ManageBilling)) {
            $overdue = Invoice::withoutTenancy()->where('status', InvoiceStatus::Open)->where('due_at', '<', now())->count();
            if ($overdue > 0) {
                $items[] = ['icon' => 'heroicon-o-exclamation-triangle', 'color' => 'danger', 'title' => trans_choice('dashboard.attention.overdue', $overdue, ['count' => $overdue]), 'url' => InvoiceResource::getUrl('index', ['tableFilters' => ['overdue' => ['isActive' => true]]])];
            }
        }

        if ($user->hasPlatformPermission(PlatformPermission::ViewClients)) {
            $trials = Subscription::withoutTenancy()->where('status', SubscriptionStatus::Trialing)->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count();
            if ($trials > 0) {
                $items[] = ['icon' => 'heroicon-o-sparkles', 'color' => 'info', 'title' => trans_choice('dashboard.attention.trials_ending', $trials, ['count' => $trials]), 'url' => RestaurantResource::getUrl('index', ['activeTab' => 'trial'])];
            }

            $notJoined = Employee::withoutTenancy()
                ->where('is_owner', true)
                ->where('created_at', '<', now()->subDays(3))
                ->whereHas('user', fn ($q) => $q->whereNull('last_login_at'))
                ->count();
            if ($notJoined > 0) {
                $items[] = ['icon' => 'heroicon-o-user-circle', 'color' => 'warning', 'title' => trans_choice('dashboard.attention.owners_not_joined', $notJoined, ['count' => $notJoined]), 'url' => RestaurantResource::getUrl()];
            }
        }

        if ($user->hasPlatformPermission(PlatformPermission::ViewSecurity)) {
            $alerts = SecurityAlert::unread()->count();
            if ($alerts > 0) {
                $items[] = ['icon' => 'heroicon-o-shield-exclamation', 'color' => 'danger', 'title' => trans_choice('dashboard.attention.security', $alerts, ['count' => $alerts]), 'url' => SecurityAlertResource::getUrl()];
            }
        }

        return $items;
    }
}
