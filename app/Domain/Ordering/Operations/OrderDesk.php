<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Operations;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Branches\Enums\StationKind;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\MarkArrived;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Events\OrderUpdated;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Payments\Actions\CollectPayment;
use App\Domain\Payments\Actions\RefundPayment;
use App\Domain\Payments\Models\Refund;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Everything staff and branch screens do to an order, in one place: each
 * action checks the operator (permission + branch), then uses the domain
 * actions, so the state machine, history, audit and broadcasts always apply.
 *
 * Failures throw OperationRefused (not allowed / not possible right now) or
 * InvalidTransition (the status moved on), both with messages for the screen.
 */
final class OrderDesk
{
    public function __construct(
        private readonly TransitionOrder $transition,
        private readonly CancelOrder $cancel,
        private readonly CollectPayment $collect,
        private readonly MarkArrived $arrived,
        private readonly BranchStock $stock,
        private readonly RefundPayment $refunds,
    ) {}

    /**
     * The next normal step: received → preparing → ready (kitchen), ready → picked up (window).
     *
     * @throws OperationRefused|InvalidTransition
     */
    public function advance(Operator $operator, Order $order): Order
    {
        $this->guard($operator, $order, P::OperateKitchen, P::OperateOrders, P::OperatePickup);

        return match ($order->status) {
            OrderStatus::Received => $this->kitchen($operator, $order, OrderStatus::Preparing),
            OrderStatus::Preparing => $this->kitchen($operator, $order, OrderStatus::Ready),
            OrderStatus::Ready => $this->complete($operator, $order),
            default => throw InvalidTransition::between($order->status, $order->status),
        };
    }

    /**
     * The window hands the order over: it must be ready and paid.
     *
     * @throws OperationRefused|InvalidTransition
     */
    public function complete(Operator $operator, Order $order): Order
    {
        $this->guard($operator, $order, P::OperatePickup, P::OperateOrders);

        if ($order->status !== OrderStatus::Ready) {
            throw OperationRefused::because('not_ready');
        }

        if ($order->payment_status !== PaymentStatus::Paid) {
            throw OperationRefused::because('unpaid');
        }

        return $this->transition->handle($order, OrderStatus::PickedUp, $operator->actorType, $operator->actorId);
    }

    /** @throws OperationRefused|InvalidTransition */
    public function cancel(Operator $operator, Order $order, string $reason): Order
    {
        $this->guard($operator, $order, P::OperateOrders, P::OperateKitchen);
        $reason = $this->reason($reason);

        return $this->cancel->byStaff($order, $operator->actorType, $operator->actorId, $reason);
    }

    /**
     * Undo a cancellation: back to the kitchen as received (not after a refund).
     *
     * @throws OperationRefused|InvalidTransition
     */
    public function reEnable(Operator $operator, Order $order, string $reason): Order
    {
        $this->guard($operator, $order, P::OperateOrders, P::OperateKitchen);

        if ($order->payment_status === PaymentStatus::Refunded) {
            throw OperationRefused::because('refunded');
        }

        return DB::transaction(function () use ($operator, $order, $reason): Order {
            $order = $this->transition->reEnable($order, $operator->actorType, $operator->actorId, $this->reason($reason));
            $order->loadMissing(['items.product', 'branch']);

            // Take the stock back that the cancellation released.
            foreach ($order->items as $item) {
                if ($item->product !== null) {
                    $this->stock->consume($item->product, $order->branch, $item->quantity);
                }
            }

            return $order;
        });
    }

    /** @throws OperationRefused|InvalidTransition */
    public function resetAsNew(Operator $operator, Order $order, string $reason): Order
    {
        $this->guardOverride($operator, $order);

        return $this->transition->resetAsNew($order, $operator->actorType, $operator->actorId, $this->reason($reason));
    }

    /** @throws OperationRefused|InvalidTransition */
    public function setStatus(Operator $operator, Order $order, OrderStatus $to, string $reason): Order
    {
        $this->guardOverride($operator, $order);

        return $this->transition->override($order, $to, $operator->actorType, $operator->actorId, $this->reason($reason));
    }

    /** @throws OperationRefused */
    public function collectPayment(Operator $operator, Order $order, string $tender): Order
    {
        $this->guard($operator, $order, P::CollectPayments);

        return $this->collect->handle($order, $tender, $operator->actorType, $operator->actorId);
    }

    /**
     * Gives an online payment back through its provider (the whole remaining
     * amount). Money taken at the counter is settled at the counter.
     *
     * @throws OperationRefused
     */
    public function refund(Operator $operator, Order $order, string $reason): Refund
    {
        $this->guard($operator, $order, P::RefundPayments);
        $reason = $this->reason($reason);
        $payment = $order->payment()->withoutGlobalScopes()->first();

        if ($payment === null || ! $payment->isOnline() || $payment->refundableMinor() <= 0) {
            throw OperationRefused::because('nothing_to_refund');
        }

        try {
            $refund = $this->refunds->handle($payment, null, $reason, $operator->actorType.':'.$operator->actorId);
        } catch (InvalidArgumentException) {
            throw OperationRefused::because('nothing_to_refund');
        }

        if ($refund->status === Refund::FAILED) {
            throw OperationRefused::because('refund_failed');
        }

        return $refund;
    }

    /** @throws OperationRefused */
    public function markArrived(Operator $operator, Order $order, ?int $laneId = null): Order
    {
        $this->guard($operator, $order, P::OperateOrders, P::OperatePickup);

        return $this->arrived->handle($order, MarkArrived::STAFF, $laneId);
    }

    /**
     * A kitchen station finished its part. The order starts cooking on the
     * first station's tap and is ready once every station is done.
     *
     * @throws OperationRefused|InvalidTransition
     */
    public function stationDone(Operator $operator, Order $order, StationKind $station): Order
    {
        $this->guard($operator, $order, P::OperateKitchen);

        if (! in_array($order->status, [OrderStatus::Received, OrderStatus::Preparing], true)) {
            throw InvalidTransition::between($order->status, OrderStatus::Ready);
        }

        return DB::transaction(function () use ($operator, $order, $station): Order {
            OrderItem::withoutTenancy()
                ->where('order_id', $order->id)
                ->whereNull('done_at')
                ->where(fn ($query) => $station === StationKind::General
                    ? $query->whereNull('station')->orWhere('station', StationKind::General->value)
                    : $query->where('station', $station->value))
                ->update(['done_at' => now()]);

            $open = OrderItem::withoutTenancy()->where('order_id', $order->id)->whereNull('done_at')->exists();

            if ($order->status === OrderStatus::Received) {
                $order = $this->transition->handle($order, OrderStatus::Preparing, $operator->actorType, $operator->actorId);
            }

            if (! $open) {
                return $this->transition->handle($order, OrderStatus::Ready, $operator->actorType, $operator->actorId, 'stations.done');
            }

            DB::afterCommit(fn () => OrderUpdated::dispatch($order, OrderUpdated::STATION_DONE));

            return $order;
        });
    }

    /**
     * The window finds an order by its pickup QR ("SHORTC-123456"), the
     * 6-digit pickup code or the short code, within the operator's branch.
     * A QR proves both codes at once ("verified").
     *
     * @return array{order: ?Order, verified: bool, matches: int}
     *
     * @throws OperationRefused
     */
    public function lookup(Operator $operator, int $branchId, string $input): array
    {
        if (! $operator->covers($branchId) || ! $operator->can(P::OperatePickup, P::OperateOrders)) {
            throw OperationRefused::because('not_allowed');
        }

        $input = strtoupper(preg_replace('/\s+/', '', $input) ?? '');
        $open = Order::withoutTenancy()
            ->with(['items', 'lane'])
            ->where('branch_id', $branchId)
            ->whereNotIn('status', [OrderStatus::PickedUp->value, OrderStatus::Cancelled->value]);

        if (preg_match('/^([2-9A-HJ-NP-Z]{6})-(\d{6})$/', $input, $m) === 1) {
            $order = (clone $open)->where('short_code', $m[1])->where('pickup_code', $m[2])->first();

            return ['order' => $order, 'verified' => $order !== null, 'matches' => $order === null ? 0 : 1];
        }

        $column = preg_match('/^\d{6}$/', $input) === 1 ? 'pickup_code' : 'short_code';
        $found = (clone $open)->where($column, $input)->latest('id')->limit(2)->get();

        return [
            'order' => $found->count() === 1 ? $found->first() : null,
            'verified' => $found->count() === 1 && $column === 'pickup_code',
            'matches' => $found->count(),
        ];
    }

    /** @throws OperationRefused|InvalidTransition */
    private function kitchen(Operator $operator, Order $order, OrderStatus $to): Order
    {
        if (! $operator->can(P::OperateKitchen, P::OperateOrders)) {
            throw OperationRefused::because('not_allowed');
        }

        if ($to === OrderStatus::Ready) {
            OrderItem::withoutTenancy()->where('order_id', $order->id)->whereNull('done_at')->update(['done_at' => now()]);
        }

        return $this->transition->handle($order, $to, $operator->actorType, $operator->actorId);
    }

    /** @throws OperationRefused */
    private function guard(Operator $operator, Order $order, P ...$any): void
    {
        if (! $operator->covers($order->branch_id) || ! $operator->can(...$any)) {
            throw OperationRefused::because('not_allowed');
        }
    }

    /** @throws OperationRefused */
    private function guardOverride(Operator $operator, Order $order): void
    {
        if (! $operator->covers($order->branch_id) || ! $operator->canOverride) {
            throw OperationRefused::because('override');
        }
    }

    /** @throws OperationRefused */
    private function reason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 2) {
            throw OperationRefused::because('reason');
        }

        return mb_substr($reason, 0, 200);
    }
}
