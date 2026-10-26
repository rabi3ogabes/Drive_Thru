<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Branches\QrCodes;
use App\Domain\ReadyCall\Enums\PagerStatus;
use App\Domain\ReadyCall\Models\PagerTicket;
use App\Domain\ReadyCall\Pager;
use App\Livewire\Ops\Concerns\OpsScreen;
use App\Support\Lang;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The counter's pager: give a guest a numbered ticket (with an optional
 * label and phone), show the QR they scan, then one tap rings their phone
 * when it's ready, and one more closes it when they collect.
 */
class PagerCounter extends Component
{
    use OpsScreen;

    public string $label = '';

    public string $phone = '';

    /** The ticket whose QR is on screen. */
    #[Locked]
    public ?int $showing = null;

    public function mount(int $branchId, ?int $deviceId = null): void
    {
        $this->branchId = $branchId;
        $this->deviceId = $deviceId;
        abort_unless($this->operator()->can(P::OperatePager), 403);
    }

    public function create(): void
    {
        $this->validate(['label' => ['nullable', 'string', 'max:60'], 'phone' => ['nullable', 'string', 'max:20']]);
        $operator = $this->operator();
        abort_unless($operator->can(P::OperatePager), 403);

        $ticket = null;
        $this->attempt(function () use (&$ticket, $operator): void {
            $ticket = app(Pager::class)->create($this->branch(), $this->label, $this->phone, app()->getLocale(), $operator->userId());
        });

        if ($ticket instanceof PagerTicket) {
            $this->showing = $ticket->id;
            $this->reset('label', 'phone');
            $this->dispatch('ops-toast', message: Lang::text('pager.counter.created', ['number' => $ticket->number]), tone: 'success');
        }
    }

    public function show(int $ticketId): void
    {
        $this->showing = $this->ticket($ticketId)->id;
    }

    public function hide(): void
    {
        $this->showing = null;
    }

    public function ready(int $ticketId): void
    {
        $ticket = $this->ticket($ticketId);
        $this->attempt(fn () => app(Pager::class)->ready($ticket), Lang::text('pager.counter.called', ['number' => $ticket->number]));
        $this->showing = null;
    }

    public function callAgain(int $ticketId): void
    {
        $ticket = $this->ticket($ticketId);
        $this->attempt(fn () => app(Pager::class)->callAgain($ticket), Lang::text('pager.counter.called', ['number' => $ticket->number]));
    }

    public function collected(int $ticketId): void
    {
        $ticket = $this->ticket($ticketId);
        $this->attempt(fn () => app(Pager::class)->collected($ticket), Lang::text('pager.counter.closed', ['number' => $ticket->number]));
    }

    public function render(): View
    {
        $open = PagerTicket::withoutTenancy()
            ->where('branch_id', $this->branchId)
            ->whereIn('status', [PagerStatus::Waiting->value, PagerStatus::Ready->value])
            ->where('expires_at', '>', now())
            ->orderBy('created_at')
            ->get();

        $shown = $this->showing === null ? null : $open->firstWhere('id', $this->showing);
        $pager = app(Pager::class);

        return view('livewire.ops.pager-counter', [
            'branch' => $this->branch(),
            'waiting' => $open->where('status', PagerStatus::Waiting)->values(),
            'ready' => $open->where('status', PagerStatus::Ready)->sortByDesc('ready_at')->values(),
            'shown' => $shown,
            'qr' => $shown === null ? null : app(QrCodes::class)->svg($pager->url($shown)),
        ]);
    }

    /** A live ticket of this branch, or 404; the screen must still run the pager. */
    private function ticket(int $ticketId): PagerTicket
    {
        abort_unless($this->operator()->can(P::OperatePager), 403);

        return PagerTicket::withoutTenancy()->where('branch_id', $this->branchId)->findOrFail($ticketId);
    }
}
