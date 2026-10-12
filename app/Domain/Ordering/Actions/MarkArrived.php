<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Branches\Models\Lane;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Events\OrderUpdated;
use App\Domain\Ordering\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The guest is at the branch: they tapped "I'm here" (and maybe chose a lane
 * or spot), staff saw them, or their shared location entered the arrival
 * radius. Recorded once; choosing a lane later still updates it. An order
 * held for "I'm on the way" goes to the kitchen at once.
 */
final class MarkArrived
{
    public const GUEST = 'guest';

    public const STAFF = 'staff';

    public const AUTO = 'auto';

    public function __construct(private readonly TransitionOrder $transition) {}

    public function handle(Order $order, string $source, ?int $laneId = null): Order
    {
        $order = $order->fresh() ?? $order;

        if ($order->status->isFinal() || $order->status === OrderStatus::PendingPayment) {
            return $order;
        }

        $lane = $laneId === null ? null : Lane::withoutTenancy()->where('branch_id', $order->branch_id)->where('is_active', true)->whereKey($laneId)->value('id');
        $changes = [];

        if ($order->arrived_at === null) {
            $changes += ['arrived_at' => CarbonImmutable::now(), 'arrival_source' => $source];
        }

        if ($lane !== null && $lane !== $order->lane_id) {
            $changes['lane_id'] = $lane;
        }

        if ($changes === []) {
            return $order;
        }

        return DB::transaction(function () use ($order, $changes): Order {
            $order->forceFill($changes)->save();

            if ($order->status === OrderStatus::Scheduled && $order->timing === OrderTiming::OnTheWay) {
                return $this->transition->handle($order, OrderStatus::Received, TransitionOrder::SYSTEM, note: 'arrived');
            }

            DB::afterCommit(fn () => OrderUpdated::dispatch($order, OrderUpdated::ARRIVED));

            return $order;
        });
    }
}
