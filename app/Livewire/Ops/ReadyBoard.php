<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\ReadyCall\Enums\PagerStatus;
use App\Domain\ReadyCall\Models\PagerTicket;
use App\Livewire\Ops\Concerns\OpsScreen;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The waiting-area TV: pager numbers in preparation and ready, the latest
 * ready number large, readable from across the room. Numbers only.
 */
class ReadyBoard extends Component
{
    use OpsScreen;

    public function mount(int $branchId, ?int $deviceId = null): void
    {
        $this->branchId = $branchId;
        $this->deviceId = $deviceId;
        $this->operator();
        $this->seen = $this->readyIds();
    }

    public function render(): View
    {
        $open = PagerTicket::withoutTenancy()
            ->where('branch_id', $this->branchId)
            ->whereIn('status', [PagerStatus::Waiting->value, PagerStatus::Ready->value])
            ->where('expires_at', '>', now())
            ->get(['id', 'number', 'status', 'created_at', 'ready_at']);

        $ready = $open->where('status', PagerStatus::Ready)->sortByDesc('ready_at')->values();

        if ($this->newlyArrived(array_values(array_map('intval', $ready->pluck('id')->all()))) !== []) {
            $this->dispatch('ops-new-orders', count: 1);
        }

        return view('livewire.ops.ready-board', [
            'branch' => $this->branch(),
            'preparing' => $open->where('status', PagerStatus::Waiting)->sortBy('created_at')->values(),
            'ready' => $ready,
            'latest' => $ready->first(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function readyIds(): array
    {
        return array_values(array_map('intval', PagerTicket::withoutTenancy()->where('branch_id', $this->branchId)
            ->where('status', PagerStatus::Ready->value)->pluck('id')->all()));
    }
}
