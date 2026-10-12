<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Integrations\Api\OrderPayload;
use App\Domain\Integrations\Models\ApiClient;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Operations\OperationRefused;
use App\Domain\Ordering\Operations\Operator;
use App\Domain\Ordering\Operations\OrderDesk;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Orders for another system: list (newest first, cursor pages), one order,
 * and moving an order along (preparing → ready → picked up, or cancelled)
 * through the same desk as the staff screens, so every rule still applies.
 */
final class OrdersController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:200'],
            'branch' => ['nullable', 'string', 'max:40'],
            'since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        $statuses = array_values(array_filter(array_map(
            fn (string $value): ?string => OrderStatus::tryFrom(trim($value))?->value,
            explode(',', (string) ($data['status'] ?? '')),
        )));

        $orders = Order::query()
            ->with(['branch', 'items', 'lane', 'customer'])
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when(filled($data['branch'] ?? null), fn ($query) => $query->whereHas('branch', fn ($branch) => $branch->where('code', $data['branch'])))
            ->when(filled($data['since'] ?? null), fn ($query) => $query->where('placed_at', '>=', CarbonImmutable::parse((string) $data['since'])->utc()))
            ->orderByDesc('id')
            ->cursorPaginate((int) ($data['per_page'] ?? 25));

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order): array => OrderPayload::from($order))->values(),
            'next_cursor' => $orders->nextCursor()?->encode(),
        ]);
    }

    public function show(int $order): JsonResponse
    {
        return response()->json(['data' => OrderPayload::from(Order::query()->findOrFail($order))]);
    }

    public function status(Request $request, int $order, OrderDesk $desk): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([OrderStatus::Preparing->value, OrderStatus::Ready->value, OrderStatus::PickedUp->value, OrderStatus::Cancelled->value])],
            'reason' => ['required_if:status,cancelled', 'nullable', 'string', 'min:2', 'max:200'],
        ]);

        $model = Order::query()->findOrFail($order);
        $client = $request->attributes->get('api_client');
        abort_unless($client instanceof ApiClient, 401);
        $operator = Operator::integration($client);
        $to = OrderStatus::from($data['status']);

        try {
            $model = match ($to) {
                OrderStatus::Cancelled => $desk->cancel($operator, $model, (string) $data['reason']),
                OrderStatus::PickedUp => $desk->complete($operator, $model),
                default => $this->kitchenTo($desk, $operator, $model, $to),
            };
        } catch (OperationRefused $refused) {
            return response()->json(['error' => ['code' => $refused->reason, 'message' => $refused->getMessage()]], 409);
        } catch (InvalidTransition $invalid) {
            return response()->json(['error' => ['code' => 'invalid_transition', 'message' => $invalid->getMessage()]], 409);
        }

        return response()->json(['data' => OrderPayload::from($model->fresh() ?? $model)]);
    }

    /** Received → preparing → ready; a POS that jumps straight to "ready" passes through "preparing". */
    private function kitchenTo(OrderDesk $desk, Operator $operator, Order $order, OrderStatus $to): Order
    {
        if ($order->status === $to) {
            return $order;
        }

        if ($order->status === OrderStatus::Received) {
            $order = $desk->advance($operator, $order);
        }

        if ($to === OrderStatus::Ready && $order->status === OrderStatus::Preparing) {
            $order = $desk->advance($operator, $order);
        }

        if ($order->status !== $to) {
            throw InvalidTransition::between($order->status, $to);
        }

        return $order;
    }
}
