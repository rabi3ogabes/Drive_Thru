<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Operations\OperationRefused;
use App\Livewire\Ops\Concerns\OpsScreen;
use App\Support\Lang;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The pickup window. Scan the guest's QR or type a code: the screen checks
 * Paid ✓ Verified ✓ Ready ✓, takes payment if needed, and completes the
 * handover. Arrived guests queue with their car and plate.
 */
class PickupWindow extends Component
{
    use OpsScreen;

    public string $code = '';

    public string $confirmCode = '';

    #[Locked]
    public ?int $orderId = null;

    /** The guest proved the order is theirs (QR or pickup code), checked on the server. */
    #[Locked]
    public bool $verified = false;

    #[Locked]
    public ?string $notice = null;

    public function mount(int $branchId, ?int $deviceId = null): void
    {
        $this->branchId = $branchId;
        $this->deviceId = $deviceId;
        abort_unless($this->operator()->can(P::OperatePickup, P::OperateOrders), 403);
    }

    public function lookup(): void
    {
        $this->notice = null;
        $input = trim($this->code);

        if ($input === '') {
            return;
        }

        try {
            $found = $this->desk()->lookup($this->operator(), $this->branchId, $input);
        } catch (OperationRefused $refused) {
            $this->notice = $refused->getMessage();

            return;
        }

        $this->code = '';
        $this->confirmCode = '';

        if ($found['order'] === null) {
            $this->orderId = null;
            $this->verified = false;
            $this->notice = $found['matches'] > 1 ? Lang::text('ops.pickup.several') : Lang::text('ops.pickup.not_found');

            return;
        }

        $this->orderId = $found['order']->id;
        $this->verified = $found['verified'];
    }

    /** Picked from the arrived queue or the ready list: verify with the guest's code next. */
    public function open(int $orderId): void
    {
        $this->orderId = $this->order($orderId)->id;
        $this->verified = false;
        $this->notice = null;
        $this->confirmCode = '';
    }

    public function verify(): void
    {
        $order = $this->current();

        if ($order === null) {
            return;
        }

        $this->verified = hash_equals($order->pickup_code, preg_replace('/\D/', '', $this->confirmCode) ?? '');
        $this->notice = $this->verified ? null : Lang::text('ops.pickup.wrong_code');
        $this->confirmCode = '';
    }

    public function collect(string $tender): void
    {
        $order = $this->current();

        if ($order !== null) {
            $this->attempt(fn () => $this->desk()->collectPayment($this->operator(), $order, $tender), Lang::text('ops.board.collected', ['number' => $order->displayNumber()]));
        }
    }

    public function complete(): void
    {
        $order = $this->current();

        if ($order === null) {
            return;
        }

        if (! $this->verified) {
            $this->notice = Lang::text('ops.pickup.verify_first');

            return;
        }

        if ($this->attempt(fn () => $this->desk()->complete($this->operator(), $order), Lang::text('ops.pickup.completed', ['number' => $order->displayNumber()]))) {
            $this->reset('orderId', 'verified', 'notice');
        }
    }

    public function clear(): void
    {
        $this->reset('orderId', 'verified', 'notice', 'code', 'confirmCode');
    }

    public function render(): View
    {
        $branch = $this->branch();
        $open = Order::withoutTenancy()
            ->with(['lane', 'customer', 'items'])
            ->where('branch_id', $this->branchId)
            ->whereIn('status', [OrderStatus::Scheduled->value, OrderStatus::Received->value, OrderStatus::Preparing->value, OrderStatus::Ready->value])
            ->get();

        $current = $this->current();

        return view('livewire.ops.pickup-window', [
            'branch' => $branch,
            'tz' => $branch->restaurant->timezone,
            'current' => $current,
            'checks' => $current === null ? [] : [
                'paid' => $current->payment_status === PaymentStatus::Paid,
                'verified' => $this->verified,
                'ready' => $current->status === OrderStatus::Ready,
            ],
            'arrived' => $open->whereNotNull('arrived_at')->sortBy('arrived_at')->values(),
            'ready' => $open->where('status', OrderStatus::Ready)->whereNull('arrived_at')->sortBy('ready_at')->values(),
            'canCollect' => $this->operator()->can(P::CollectPayments),
        ]);
    }

    private function current(): ?Order
    {
        return $this->orderId === null ? null : Order::withoutTenancy()->with(['items', 'lane', 'customer'])->where('branch_id', $this->branchId)->find($this->orderId);
    }
}
