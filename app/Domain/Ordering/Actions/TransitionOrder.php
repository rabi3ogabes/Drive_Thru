<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderStatusChange;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The single way an order changes status. It checks the move against the
 * state machine, saves it with a compare-and-set (the row only changes if it
 * still has the status we read, so a second tap or a stale screen fails
 * cleanly), stamps the status time, writes the history and, after commit,
 * fires OrderStatusChanged (which the live screens and the guest receive).
 *
 * Override moves (re-enable, reset as new, any status) are explicit, need a
 * reason and are audited. Callers check the override permission first.
 */
final class TransitionOrder
{
    public const CUSTOMER = 'customer';

    public const STAFF = 'staff';

    public const DEVICE = 'device';

    public const SYSTEM = 'system';

    /** Another system through the public API (actor id = the API client). */
    public const INTEGRATION = 'integration';

    /** Columns cleared when an order starts over as new. */
    private const PROGRESS = ['preparing_at', 'ready_at', 'picked_up_at', 'cancelled_at', 'cancel_reason'];

    /**
     * @throws InvalidTransition
     */
    public function handle(Order $order, OrderStatus $to, string $actorType = self::SYSTEM, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->move($order, $to, $actorType, $actorId, $note, override: false);
    }

    /**
     * Any status a person with the override permission chooses (except back
     * to awaiting payment). A reason is required and the change is audited.
     *
     * @throws InvalidTransition
     */
    public function override(Order $order, OrderStatus $to, string $actorType, int $actorId, string $reason): Order
    {
        return $this->move($order, $to, $actorType, $actorId, $reason, override: true);
    }

    /**
     * A cancelled order goes back to the kitchen as received.
     *
     * @throws InvalidTransition
     */
    public function reEnable(Order $order, string $actorType, int $actorId, string $reason): Order
    {
        if ($order->status !== OrderStatus::Cancelled) {
            throw InvalidTransition::between($order->status, OrderStatus::Received);
        }

        return $this->override($order, OrderStatus::Received, $actorType, $actorId, $reason);
    }

    /**
     * Starts the order over as received: progress, item checkmarks and
     * cancellation are cleared, and a fresh ready time is set.
     *
     * @throws InvalidTransition
     */
    public function resetAsNew(Order $order, string $actorType, int $actorId, string $reason): Order
    {
        return $this->move($order, OrderStatus::Received, $actorType, $actorId, $reason, override: true, reset: true);
    }

    /** The first history entry, written when the order is created. */
    public function start(Order $order, string $actorType = self::CUSTOMER, ?int $actorId = null): void
    {
        $this->record($order, null, $order->status, $actorType, $actorId, null);

        DB::afterCommit(fn () => OrderStatusChanged::dispatch($order, null, $order->status));
    }

    /**
     * @throws InvalidTransition
     */
    private function move(Order $order, OrderStatus $to, string $actorType, ?int $actorId, ?string $note, bool $override, bool $reset = false): Order
    {
        $note = $note !== null ? trim($note) : null;

        if ($override && ($note === null || $note === '')) {
            throw InvalidTransition::reasonRequired();
        }

        return DB::transaction(function () use ($order, $to, $actorType, $actorId, $note, $override, $reset): Order {
            $fresh = Order::withoutTenancy()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $fresh->status;

            $allowed = $override ? $from->canOverrideTo($to) || ($reset && $from === OrderStatus::Received) : $from->canMoveTo($to);

            if (! $allowed) {
                throw InvalidTransition::between($from, $to);
            }

            $now = CarbonImmutable::now();
            $changes = ['status' => $to->value, 'updated_at' => $now];

            if ($column = $to->timestampColumn()) {
                $changes[$column] = $now;
            }

            if ($to === OrderStatus::Cancelled) {
                $changes['cancel_reason'] = $note !== null ? mb_substr($note, 0, 200) : null;
            }

            if ($to === OrderStatus::Received) {
                // Released, re-enabled or reset: the kitchen starts now.
                $changes['prepare_at'] = $fresh->prepare_at !== null && $fresh->prepare_at->isFuture() && ! $override ? $fresh->prepare_at : $now;
                $changes['ready_estimate_at'] = $now->addMinutes($fresh->prep_minutes);
                $changes['cancelled_at'] = null;
                $changes['cancel_reason'] = null;
            }

            if ($reset) {
                foreach (self::PROGRESS as $column) {
                    $changes[$column] = null;
                }
            }

            // Compare-and-set: only if nobody changed the status since we read it.
            $saved = Order::withoutTenancy()->whereKey($fresh->id)->where('status', $from->value)->update($changes);

            if ($saved !== 1) {
                throw InvalidTransition::stale($from, $to);
            }

            if ($reset) {
                OrderItem::withoutTenancy()->where('order_id', $fresh->id)->update(['done_at' => null]);
            }

            $this->record($fresh, $from, $to, $actorType, $actorId, $note);

            if ($override) {
                $log = activity()->performedOn($fresh);
                $causer = $actorType === self::STAFF && $actorId !== null ? User::query()->find($actorId) : null;

                if ($causer !== null) {
                    $log->causedBy($causer);
                }

                $log
                    ->event($reset ? 'order.reset' : ($from === OrderStatus::Cancelled ? 'order.re_enabled' : 'order.overridden'))
                    ->withProperties(['from' => $from->value, 'to' => $to->value, 'reason' => $note, 'actor' => $actorType.':'.$actorId])
                    ->log($reset ? 'order.reset' : 'order.overridden');
            }

            $order->setRawAttributes(Order::withoutTenancy()->findOrFail($fresh->id)->getAttributes(), true);

            DB::afterCommit(fn () => OrderStatusChanged::dispatch($order, $from, $to));

            return $order;
        });
    }

    private function record(Order $order, ?OrderStatus $from, OrderStatus $to, string $actorType, ?int $actorId, ?string $note): void
    {
        OrderStatusChange::withoutTenancy()->create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'from' => $from,
            'to' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note !== null ? mb_substr($note, 0, 200) : null,
            'created_at' => now(),
        ]);
    }
}
