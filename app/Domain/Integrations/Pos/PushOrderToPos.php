<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Pos;

use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Ordering\Events\OrderUpdated;
use App\Domain\Ordering\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one accepted order to the restaurant's POS. Network and server
 * errors are retried (30 s, 2 min, 10 min, 30 min); a refusal (4xx) is not.
 * The outcome is on the order, so staff see "in POS" or why not.
 */
final class PushOrderToPos implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $orderId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $order = Order::withoutTenancy()->find($this->orderId);

        if ($order === null || $order->pos_reference !== null || $order->pos_synced_at !== null) {
            return;
        }

        $driver = self::driverFor($order->restaurant_id);

        if ($driver === null) {
            return;
        }

        try {
            $reference = $driver->pushOrder($order);
        } catch (PosRejected $rejected) {
            $this->record($order, ['pos_error' => $rejected->status > 0 ? 'HTTP '.$rejected->status : $rejected->getMessage()]);

            if (! $rejected->isPermanent() && $rejected->status > 0) {
                $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
            }

            return;
        } catch (ProviderUnavailable) {
            $this->record($order, ['pos_error' => 'unreachable']);
            $this->release($this->backoff()[min($this->attempts() - 1, 3)]);

            return;
        }

        $this->record($order, ['pos_reference' => $reference, 'pos_synced_at' => now(), 'pos_error' => null]);
    }

    public static function driverFor(int $restaurantId): ?PosDriver
    {
        $integration = Integration::withoutTenancy()
            ->where('restaurant_id', $restaurantId)
            ->where('kind', IntegrationKind::Pos->value)
            ->where('is_enabled', true)
            ->first();

        return $integration?->provider === GenericPosDriver::NAME
            ? app()->make(GenericPosDriver::class, ['integration' => $integration])
            : null;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function record(Order $order, array $changes): void
    {
        $order->forceFill($changes)->save();
        OrderUpdated::dispatch($order, OrderUpdated::POS);
    }
}
