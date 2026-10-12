<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Ordering\BranchPulse;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Livewire\Ops\Concerns\OpsScreen;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The branch TV: what's in the kitchen, ready and late, sales today against
 * yesterday and last week, the day's hourly sales and live alerts. Read
 * only, sized to be read from across the room.
 */
class CommandCenter extends Component
{
    use OpsScreen;

    public function mount(int $branchId, ?int $deviceId = null): void
    {
        $this->branchId = $branchId;
        $this->deviceId = $deviceId;
        $this->operator();
    }

    public function render(): View
    {
        $branch = $this->branch();

        return view('livewire.ops.command-center', [
            'branch' => $branch,
            'pulse' => app(BranchPulse::class)->of($branch),
            'readyNumbers' => Order::withoutTenancy()
                ->where('branch_id', $this->branchId)
                ->where('status', OrderStatus::Ready->value)
                ->orderBy('ready_at')
                ->limit(12)
                ->get(['id', 'number', 'arrived_at']),
        ]);
    }
}
