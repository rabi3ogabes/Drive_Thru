<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Enums\BranchState;
use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Operations\OperationRefused;
use App\Domain\Ordering\Operations\Operator;
use App\Domain\Ordering\Operations\OrderDesk;
use App\Domain\Ordering\PassSnapshot;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Lang;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The owner's live overview as a kitchen pass: one branch at a time, its open
 * orders hanging as tickets per station and ageing live, what to start next,
 * and what just went out. Same access as the classic overview; "Start" goes
 * through the order desk and only for people who run the kitchen.
 */
class PassBoard extends Component
{
    #[Locked]
    public int $restaurantId;

    #[Url(as: 'branch')]
    public ?int $branchId = null;

    public ?int $selected = null;

    #[Locked]
    public bool $canStart = false;

    public function mount(int $restaurantId): void
    {
        $this->restaurantId = $restaurantId;
        $this->authorizeViewer();
        $this->branchId = $this->resolveBranch($this->branchId)?->id;
        $this->canStart = $this->operator()->can(P::OperateKitchen, P::OperateOrders);
    }

    public function selectBranch(int $branchId): void
    {
        $this->authorizeViewer();
        $this->branchId = $this->resolveBranch($branchId)?->id;
        $this->selected = null;
    }

    public function select(int $orderId): void
    {
        $this->selected = $this->selected === $orderId ? null : $orderId;
    }

    /** Received → preparing, through the desk. Never moves an order that has already started. */
    public function start(int $orderId): void
    {
        $this->authorizeViewer();
        $operator = $this->operator();

        if (! $operator->can(P::OperateKitchen, P::OperateOrders)) {
            $this->dispatch('ops-toast', message: Lang::text('ops.pass.not_allowed'), tone: 'error');

            return;
        }

        $order = Order::withoutTenancy()
            ->where('restaurant_id', $this->restaurantId)
            ->whereIn('branch_id', $this->branches()->modelKeys())
            ->with(['items', 'lane'])
            ->findOrFail($orderId);

        if ($order->status !== OrderStatus::Received) {
            $this->dispatch('ops-toast', message: Lang::text('ops.pass.already_started', ['number' => $order->displayNumber()]), tone: 'error');

            return;
        }

        try {
            app(OrderDesk::class)->advance($operator, $order);
        } catch (OperationRefused|InvalidTransition $problem) {
            $this->dispatch('ops-toast', message: $problem->getMessage(), tone: 'error');

            return;
        }

        $this->dispatch('ops-toast', message: Lang::text('ops.pass.started', ['number' => $order->displayNumber()]), tone: 'success');
    }

    public function render(): View
    {
        $this->authorizeViewer();
        $restaurant = Restaurant::query()->findOrFail($this->restaurantId);
        $branches = $this->branches();
        $branch = $this->resolveBranch($this->branchId);
        $this->branchId = $branch?->id;

        $schedule = app(BranchSchedule::class);
        $state = $branch === null ? null : $schedule->state($branch);

        return view('livewire.ops.pass-board', [
            'restaurant' => $restaurant,
            'branches' => $branches,
            'branch' => $branch,
            'state' => $state,
            'closed' => $state !== null && ! in_array($state, [BranchState::Open, BranchState::Busy], true),
            'opensAt' => $branch !== null && $state !== null && ! in_array($state, [BranchState::Open, BranchState::Busy], true) ? $schedule->nextOpening($branch) : null,
            'snapshot' => $branch === null ? null : app(PassSnapshot::class)->of($branch),
        ]);
    }

    /**
     * The branches this person covers, in the restaurant's order.
     *
     * @return Collection<int, Branch>
     */
    private function branches(): Collection
    {
        return once(function (): Collection {
            $operator = $this->operator();

            return Branch::withoutTenancy()
                ->with('restaurant')
                ->where('restaurant_id', $this->restaurantId)
                ->orderBy('sort')
                ->get()
                ->filter(fn (Branch $branch): bool => $operator->covers($branch))
                ->values();
        });
    }

    /** The requested branch when this person covers it, otherwise their first. */
    private function resolveBranch(?int $branchId): ?Branch
    {
        $branches = $this->branches();

        return $branches->first(fn (Branch $branch): bool => $branch->id === $branchId) ?? $branches->first();
    }

    private function operator(): Operator
    {
        return once(fn (): Operator => Operator::staff($this->user(), $this->restaurantId));
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function authorizeViewer(): void
    {
        abort_unless(app(ChannelAccess::class)->restaurantDashboard($this->user(), $this->restaurantId), 403);
    }
}
