<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Ordering\BranchPulse;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Every branch at a glance for the owner and general manager: a health card
 * per branch (Healthy / Busy / Needs attention, always written out), live
 * counts, sales today and what needs looking at.
 */
class ExecutiveBoard extends Component
{
    #[Locked]
    public int $restaurantId;

    public function mount(int $restaurantId): void
    {
        $this->restaurantId = $restaurantId;
        $this->authorizeViewer();
    }

    public function render(): View
    {
        $this->authorizeViewer();
        $restaurant = Restaurant::query()->findOrFail($this->restaurantId);
        $pulse = app(BranchPulse::class);
        $schedule = app(BranchSchedule::class);

        $branches = Branch::withoutTenancy()
            ->with(['restaurant', 'hours', 'closures'])
            ->where('restaurant_id', $this->restaurantId)
            ->orderBy('sort')
            ->get()
            ->map(fn (Branch $branch): array => ['branch' => $branch, 'pulse' => $pulse->of($branch), 'state' => $schedule->state($branch)]);

        return view('livewire.ops.executive-board', [
            'restaurant' => $restaurant,
            'branches' => $branches,
            'totals' => [
                'sales' => $branches->sum(fn (array $b): int => $b['pulse']['sales_today']),
                'yesterday' => $branches->sum(fn (array $b): int => $b['pulse']['sales_yesterday']),
                'orders' => $branches->sum(fn (array $b): int => $b['pulse']['orders_today']),
                'open' => $branches->sum(fn (array $b): int => $b['pulse']['new'] + $b['pulse']['preparing'] + $b['pulse']['ready']),
                'delayed' => $branches->sum(fn (array $b): int => $b['pulse']['delayed']),
            ],
        ]);
    }

    private function authorizeViewer(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && app(ChannelAccess::class)->restaurantDashboard($user, $this->restaurantId), 403);
    }
}
