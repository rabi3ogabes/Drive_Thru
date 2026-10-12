<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Events\OrderUpdated;
use App\Domain\Ordering\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Opt-in live location while the guest drives over. Each ping stores the
 * distance to the branch (the exact position is kept only on the order, for
 * this trip). Inside the approach radius the kitchen is told to start ("prepare
 * now", and a held "on my way" order is released); inside the arrival radius
 * the guest is marked arrived when the branch allows it. Pings faster than the
 * branch's interval are ignored, and declining is recorded so staff know.
 */
final class TrackGuestLocation
{
    public function __construct(
        private readonly TransitionOrder $transition,
        private readonly MarkArrived $arrived,
    ) {}

    /**
     * @return array{distance_m: ?int, eta_minutes: ?int, arrived: bool}
     */
    public function ping(Order $order, float $latitude, float $longitude): array
    {
        $order->loadMissing('branch');
        $branch = $order->branch;

        if ($order->status->isFinal() || $order->status === OrderStatus::PendingPayment || ! $branch->tracking_enabled || ! $branch->hasLocation()
            || abs($latitude) > 90 || abs($longitude) > 180) {
            return ['distance_m' => null, 'eta_minutes' => null, 'arrived' => $order->arrived_at !== null];
        }

        $now = CarbonImmutable::now();
        $distance = (int) round(self::metres($latitude, $longitude, (float) $branch->latitude, (float) $branch->longitude));
        $eta = $this->eta($distance, $branch->avg_speed_kmh);

        if ($order->last_location_at !== null && $order->last_location_at->greaterThan($now->subSeconds(max(5, intdiv($branch->location_ping_seconds, 2))))) {
            return ['distance_m' => $order->last_distance_m, 'eta_minutes' => $this->eta($order->last_distance_m, $branch->avg_speed_kmh), 'arrived' => $order->arrived_at !== null];
        }

        DB::transaction(function () use ($order, $branch, $latitude, $longitude, $distance, $now): void {
            $order->forceFill([
                'location_consent_at' => $order->location_consent_at ?? $now,
                'location_denied_at' => null,
                'last_latitude' => round($latitude, 6),
                'last_longitude' => round($longitude, 6),
                'last_distance_m' => $distance,
                'last_location_at' => $now,
            ])->save();

            if ($distance <= $branch->arrival_radius_m && $branch->auto_arrival && $order->arrived_at === null) {
                $this->arrived->handle($order, MarkArrived::AUTO);

                return;
            }

            if ($distance <= $branch->approach_radius_m && $order->approach_alerted_at === null) {
                $order->forceFill(['approach_alerted_at' => $now, 'on_the_way_at' => $order->on_the_way_at ?? $now])->save();

                if ($order->status === OrderStatus::Scheduled && $order->timing === OrderTiming::OnTheWay) {
                    $this->transition->handle($order, OrderStatus::Received, TransitionOrder::SYSTEM, note: 'approaching');

                    return;
                }

                DB::afterCommit(fn () => OrderUpdated::dispatch($order, OrderUpdated::APPROACHING));

                return;
            }

            DB::afterCommit(fn () => OrderUpdated::dispatch($order, OrderUpdated::LOCATION));
        });

        $order->refresh();

        return ['distance_m' => $distance, 'eta_minutes' => $eta, 'arrived' => $order->arrived_at !== null];
    }

    /** The guest said no (or the browser blocked it): staff see "location off". */
    public function denied(Order $order): void
    {
        if ($order->status->isFinal() || $order->location_denied_at !== null) {
            return;
        }

        $order->forceFill(['location_denied_at' => now()])->save();
        DB::afterCommit(fn () => OrderUpdated::dispatch($order, OrderUpdated::LOCATION));
    }

    /** Great-circle distance in metres. */
    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6_371_000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    private function eta(?int $metres, int $speedKmh): ?int
    {
        if ($metres === null) {
            return null;
        }

        return max(1, (int) ceil($metres / 1000 / max(5, $speedKmh) * 60));
    }
}
