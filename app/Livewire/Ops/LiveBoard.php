<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Ordering\BranchPulse;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Livewire\Ops\Concerns\OpsScreen;
use App\Support\Lang;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL as Links;
use Livewire\Attributes\Url;
use Livewire\Component;
use SensitiveParameter;

/**
 * The cashier / manager live board: KPIs, every open order with its car,
 * payment and kitchen status, filters, list or box view, a ticker, the done
 * table by day, and an order panel with every action the person may take.
 */
class LiveBoard extends Component
{
    use OpsScreen;

    public const FILTERS = ['all', 'new', 'preparing', 'ready', 'delayed', 'scheduled', 'completed', 'cancelled'];

    public const RANGES = ['today', 'yesterday', 'week', 'month', 'day'];

    #[Url(except: 'all')]
    public string $filter = 'all';

    #[Url(except: 'list')]
    public string $view = 'list';

    #[Url(as: 'done', except: 'today')]
    public string $range = 'today';

    #[Url(except: '')]
    public string $day = '';

    public ?int $selected = null;

    public int $doneLimit = 25;

    public function mount(int $branchId, ?int $deviceId = null): void
    {
        $this->branchId = $branchId;
        $this->deviceId = $deviceId;
        $this->operator();
        $this->seen = array_values(array_map('intval', Order::withoutTenancy()->where('branch_id', $branchId)->where('status', OrderStatus::Received->value)->pluck('id')->all()));
    }

    public function updated(string $property): void
    {
        if ($property === 'filter' && ! in_array($this->filter, self::FILTERS, true)) {
            $this->filter = 'all';
        }
        if ($property === 'view' && ! in_array($this->view, ['list', 'box'], true)) {
            $this->view = 'list';
        }
        if ($property === 'range' && ! in_array($this->range, self::RANGES, true)) {
            $this->range = 'today';
        }
        if (in_array($property, ['range', 'day'], true)) {
            $this->doneLimit = 25;
        }
    }

    public function select(?int $orderId): void
    {
        $this->selected = $orderId === null ? null : $this->order($orderId)->id;
    }

    public function advance(int $orderId): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->advance($this->operator(), $order), Lang::text('ops.board.updated', ['number' => $order->displayNumber()]));
    }

    public function collect(int $orderId, string $tender): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->collectPayment($this->operator(), $order, $tender), Lang::text('ops.board.collected', ['number' => $order->displayNumber()]));
    }

    public function arrived(int $orderId, ?int $laneId = null): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->markArrived($this->operator(), $order, $laneId), Lang::text('ops.board.arrived', ['number' => $order->displayNumber()]));
    }

    public function cancel(int $orderId, string $reason): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->cancel($this->operator(), $order, $reason), Lang::text('ops.kitchen.cancelled', ['number' => $order->displayNumber()]));
    }

    public function reEnable(int $orderId, string $reason): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->reEnable($this->operator(), $order, $reason), Lang::text('ops.kitchen.re_enabled', ['number' => $order->displayNumber()]));
    }

    public function resetAsNew(int $orderId, string $reason, #[SensitiveParameter] ?string $pin = null): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->resetAsNew($this->personFor($pin, 'override'), $order, $reason), Lang::text('ops.board.reset', ['number' => $order->displayNumber()]));
    }

    public function setStatus(int $orderId, string $status, string $reason, #[SensitiveParameter] ?string $pin = null): void
    {
        $order = $this->order($orderId);
        $to = OrderStatus::tryFrom($status);

        if ($to === null) {
            return;
        }

        $this->attempt(fn () => $this->desk()->setStatus($this->personFor($pin, 'override'), $order, $to, $reason), Lang::text('ops.board.updated', ['number' => $order->displayNumber()]));
    }

    public function refund(int $orderId, string $reason, #[SensitiveParameter] ?string $pin = null): void
    {
        $order = $this->order($orderId);
        $this->attempt(fn () => $this->desk()->refund($this->personFor($pin, 'refund'), $order, $reason), Lang::text('ops.board.refunded', ['number' => $order->displayNumber()]));
    }

    public function showMore(): void
    {
        $this->doneLimit = min(500, $this->doneLimit + 50);
    }

    public function render(): View
    {
        $branch = $this->branch();
        $tz = $branch->restaurant->timezone;
        $operator = $this->operator();

        $open = Order::withoutTenancy()
            ->with(['items', 'lane', 'customer'])
            ->where('branch_id', $this->branchId)
            ->whereIn('status', [OrderStatus::PendingPayment->value, OrderStatus::Scheduled->value, OrderStatus::Received->value, OrderStatus::Preparing->value, OrderStatus::Ready->value])
            ->orderBy('placed_at')
            ->get();

        $isDelayed = fn (Order $o): bool => in_array($o->status, [OrderStatus::Received, OrderStatus::Preparing], true) && $o->received_at?->addMinutes($o->prep_minutes)->isPast() === true;

        $rows = match ($this->filter) {
            'new' => $open->where('status', OrderStatus::Received),
            'preparing' => $open->where('status', OrderStatus::Preparing),
            'ready' => $open->where('status', OrderStatus::Ready),
            'delayed' => $open->filter($isDelayed),
            'scheduled' => $open->whereIn('status', [OrderStatus::Scheduled, OrderStatus::PendingPayment]),
            default => $open,
        };

        $fresh = $this->newlyArrived(array_values(array_map('intval', $open->where('status', OrderStatus::Received)->modelKeys())));

        if ($fresh !== []) {
            $this->dispatch('ops-new-orders', count: count($fresh));
        }

        $doneQuery = $this->doneQuery($tz);
        $done = (clone $doneQuery)->with(['customer', 'lane'])->limit($this->doneLimit + 1)->get();
        $selected = $this->selected === null ? null : Order::withoutTenancy()->with(['items', 'lane', 'customer', 'history', 'payment'])->where('branch_id', $this->branchId)->find($this->selected);

        return view('livewire.ops.live-board', [
            'branch' => $branch,
            'tz' => $tz,
            'pulse' => app(BranchPulse::class)->of($branch),
            'rows' => in_array($this->filter, ['completed', 'cancelled'], true) ? collect() : $rows->values(),
            'open' => $open,
            'done' => $done->take($this->doneLimit),
            'doneMore' => $done->count() > $this->doneLimit,
            'doneTotal' => (int) (clone $doneQuery)->sum('total_minor'),
            'fresh' => $fresh,
            'delayedIds' => $open->filter($isDelayed)->modelKeys(),
            'selectedOrder' => $selected,
            'staffLink' => $selected === null ? null : Links::temporarySignedRoute('short.order.staff', now()->addHours(12), ['code' => $selected->short_code]),
            'can' => [
                'operate' => $operator->can(P::OperateOrders, P::OperateKitchen),
                'collect' => $operator->can(P::CollectPayments),
                'pickup' => $operator->can(P::OperatePickup, P::OperateOrders),
                // On a paired screen these ask for the PIN of someone allowed to do them.
                'override' => $this->asksForPin() ? $branch->restaurant->order_override_enabled : $operator->canOverride,
                'refund' => $this->asksForPin() || $operator->can(P::RefundPayments),
                'pin' => $this->asksForPin(),
                'contacts' => $operator->can(P::ViewCustomerContacts),
            ],
            'lanes' => $branch->lanes->where('is_active', true)->values(),
        ]);
    }

    /**
     * Finished orders for the chosen day or range (picked up, or cancelled when filtered).
     *
     * @return Builder<Order>
     */
    private function doneQuery(string $tz): Builder
    {
        $today = CarbonImmutable::now($tz)->startOfDay();
        $chosen = $this->day !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $this->day, $tz) : false;

        [$from, $to] = match ($this->range) {
            'yesterday' => [$today->subDay(), $today],
            'week' => [$today->subDays(6), $today->addDay()],
            'month' => [$today->startOfMonth(), $today->addDay()],
            'day' => $chosen instanceof CarbonImmutable ? [$chosen->startOfDay(), $chosen->startOfDay()->addDay()] : [$today, $today->addDay()],
            default => [$today, $today->addDay()],
        };

        $status = $this->filter === 'cancelled' ? OrderStatus::Cancelled : OrderStatus::PickedUp;
        $column = $status === OrderStatus::Cancelled ? 'cancelled_at' : 'picked_up_at';

        return Order::withoutTenancy()
            ->where('branch_id', $this->branchId)
            ->where('status', $status->value)
            ->whereBetween($column, [$from->utc(), $to->utc()])
            ->latest($column);
    }
}
