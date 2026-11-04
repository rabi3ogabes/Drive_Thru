<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Feedback\RatingStats;
use App\Domain\Services\Enums\Service;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Filament\App\Resources\Ratings\RatingResource;
use App\Filament\App\Workspace;
use App\Support\Digits;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The live pulse of the restaurant: branches open right now, the team,
 * screens online, the account and, for people who see reports, the last
 * 30 days of guest ratings. Order and sales figures join in Phase 8.
 */
class RestaurantOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Cheap aggregate queries: render immediately instead of flashing an empty card.
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $restaurant = Workspace::restaurant();
        $user = Workspace::user();
        $schedule = app(BranchSchedule::class);

        $branches = Workspace::scopeToBranches(Branch::query())->with(['hours', 'closures'])->get()
            ->each(fn (Branch $branch) => $branch->setRelation('restaurant', $restaurant));
        $open = $branches->filter(fn (Branch $branch): bool => $schedule->state($branch)->acceptsOrders())->count();
        $paused = $branches->filter(fn (Branch $branch): bool => $branch->isPaused())->count();

        $active = Employee::query()->where('status', EmployeeStatus::Active)->count();
        $invited = Employee::query()->where('status', EmployeeStatus::Invited)->count();

        $screens = Workspace::scopeToBranches(Device::query()->whereNull('revoked_at')->whereNotNull('token_hash'), 'branch_id')->get();
        $online = $screens->filter(fn (Device $device): bool => $device->isOnline())->count();

        $employment = $user->employmentAt($restaurant);
        $role = match (true) {
            $employment?->is_owner === true => __('staff.owner'),
            $employment?->position !== null => $employment->position->name,
            $user->isSuperAdmin() => __('staff.platform_admin'),
            default => '—',
        };

        $stats = [
            Stat::make(__('dashboard.app.stats.branches_open'), __('dashboard.app.stats.of', ['count' => Digits::number($open), 'total' => Digits::number($branches->count())]))
                ->description($paused > 0
                    ? trans_choice('dashboard.app.stats.branches_paused', $paused, ['count' => Digits::number($paused)])
                    : ($branches->isEmpty() ? __('dashboard.app.stats.no_branches') : __('dashboard.app.stats.branches_hint')))
                ->descriptionIcon($paused > 0 ? 'heroicon-m-pause-circle' : 'heroicon-m-building-storefront')
                ->color($paused > 0 ? 'warning' : ($open > 0 ? 'success' : 'gray')),
            Stat::make(__('dashboard.app.stats.team'), Digits::number($active))
                ->description(trans_choice('dashboard.app.stats.team_invited', $invited, ['count' => Digits::number($invited)]))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray'),
            Stat::make(__('dashboard.app.stats.screens'), __('dashboard.app.stats.of', ['count' => Digits::number($online), 'total' => Digits::number($screens->count())]))
                ->description($screens->isEmpty() ? __('dashboard.app.stats.no_screens') : __('dashboard.app.stats.screens_hint'))
                ->descriptionIcon('heroicon-m-computer-desktop')
                ->color($screens->isNotEmpty() && $online < $screens->count() ? 'danger' : 'gray'),
            Stat::make(__('dashboard.app.stats.status'), $restaurant->status->getLabel())
                ->description(__('dashboard.app.stats.signed_in_as', ['role' => $role]))
                ->descriptionIcon($restaurant->isOperational() ? 'heroicon-m-check-badge' : 'heroicon-m-pause-circle')
                ->color($restaurant->status->getColor()),
        ];

        if ($this->showsRating()) {
            $now = CarbonImmutable::now($restaurant->timezone);
            $rating = RatingStats::for($restaurant->id, Workspace::branchIds(), $now->subDays(29)->startOfDay(), $now);

            $stats[] = Stat::make(__('feedback.admin.stat'), $rating['average'] !== null ? number_format($rating['average'], 1).' ★' : '—')
                ->description($rating['count'] > 0 ? trans_choice('feedback.admin.stat_count', $rating['count'], ['count' => Digits::number($rating['count'])]) : __('feedback.admin.stat_none'))
                ->descriptionIcon('heroicon-m-star')
                ->color(match (true) {
                    $rating['average'] === null => 'gray',
                    $rating['average'] >= 4 => 'success',
                    $rating['average'] >= 3 => 'warning',
                    default => 'danger',
                })
                ->url(RatingResource::getUrl());
        }

        return $stats;
    }

    private function showsRating(): bool
    {
        return Workspace::can(RestaurantPermission::ViewReports) && Workspace::restaurant()->hasService(Service::DriveThru);
    }

    protected function getColumns(): int|array|null
    {
        return ['default' => 2, 'xl' => $this->showsRating() ? 5 : 4];
    }
}
