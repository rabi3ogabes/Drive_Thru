<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\Operations\Operator;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Operations screens for signed-in staff: /ops/{restaurant}/{branch}/{screen}.
 * The same Livewire screens run on paired branch devices at /screen.
 */
final class OpsController
{
    /** screen => [Livewire component, permissions (any)] */
    public const SCREENS = [
        'kitchen' => ['ops.kitchen-display', [P::OperateKitchen, P::OperateOrders]],
        'live' => ['ops.live-board', [P::ViewOrders]],
        'pickup' => ['ops.pickup-window', [P::OperatePickup, P::OperateOrders]],
        'tv' => ['ops.command-center', [P::ViewOrders]],
        'pager' => ['ops.pager-counter', [P::OperatePager]],
        'ready' => ['ops.ready-board', [P::OperatePager, P::ViewOrders]],
        'stamps' => ['ops.stamp-counter', [P::CollectPayments, P::OperatePager]],
    ];

    /** The service each screen belongs to: the counter pager is Order-Ready Call, stamps are Rewards, the rest Drive-Thru. */
    public static function serviceFor(string|DeviceKind $screen): Service
    {
        $key = $screen instanceof DeviceKind ? $screen->value : $screen;

        return match (true) {
            in_array($key, ['pager', 'ready', 'ready_board'], true) => Service::ReadyCall,
            $key === 'stamps' => Service::Rewards,
            default => Service::DriveThru,
        };
    }

    /**
     * The screens this person may open at a branch of this restaurant.
     *
     * @return list<string>
     */
    public static function screensFor(Restaurant $restaurant, Operator $operator): array
    {
        return array_values(array_filter(array_keys(self::SCREENS), fn (string $screen): bool => $restaurant->hasService(self::serviceFor($screen)) && $operator->can(...self::SCREENS[$screen][1])));
    }

    /** /ops/{restaurant}: the overview for owners, otherwise the first screen they may use. */
    public function home(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $user = $this->user($request);

        if (app(ChannelAccess::class)->restaurantDashboard($user, $restaurant->id)) {
            return redirect()->route('ops.overview', $restaurant);
        }

        $operator = Operator::staff($user, $restaurant->id);
        $branch = $this->branches($restaurant, $operator)->first();
        abort_if($branch === null, 403);

        $screen = self::screensFor($restaurant, $operator)[0] ?? null;
        abort_if($screen === null, 403);

        return redirect()->route('ops.screen', [$restaurant, $branch->code, $screen]);
    }

    /** /ops/{restaurant}/go/{screen}: that screen at the first branch the person covers. */
    public function go(Request $request, Restaurant $restaurant, string $screen): RedirectResponse
    {
        abort_unless(isset(self::SCREENS[$screen]), 404);

        $branch = $this->branches($restaurant, Operator::staff($this->user($request), $restaurant->id))->first();
        abort_if($branch === null, 403);

        return redirect()->route('ops.screen', [$restaurant, $branch->code, $screen]);
    }

    public function overview(Request $request, Restaurant $restaurant): View
    {
        abort_unless(app(ChannelAccess::class)->restaurantDashboard($this->user($request), $restaurant->id), 403);

        return view('ops.page', [
            'title' => __('ops.screens.overview').' · '.$restaurant->name,
            'screen' => 'ops.executive-board',
            'params' => ['restaurantId' => $restaurant->id],
        ]);
    }

    public function screen(Request $request, Restaurant $restaurant, string $branch, string $screen): View
    {
        abort_unless(isset(self::SCREENS[$screen]), 404);
        [$component, $permissions] = self::SCREENS[$screen];

        $model = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('code', $branch)->firstOrFail();
        $operator = Operator::staff($this->user($request), $restaurant->id);
        abort_unless($operator->covers($model) && $operator->can(...$permissions), 403);
        abort_unless($restaurant->hasService(self::serviceFor($screen)), 403, __('pager.service_off_screen'));

        $params = ['branchId' => $model->id];
        if ($screen === 'kitchen' && is_string($station = $request->query('station'))) {
            $params['station'] = $station;
        }

        return view('ops.page', [
            'title' => __('ops.screens.'.$screen).' · '.$model->name,
            'screen' => $component,
            'params' => $params,
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);

        return $user;
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(Restaurant $restaurant, Operator $operator): Collection
    {
        return Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->orderBy('sort')->get()
            ->filter(fn (Branch $branch): bool => $operator->covers($branch))->values();
    }
}
