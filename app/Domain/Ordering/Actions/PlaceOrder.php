<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Lane;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Ordering\Capacity;
use App\Domain\Ordering\CartPricer;
use App\Domain\Ordering\Data\CheckoutBenefits;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Data\PricedLine;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Exceptions\OrderRejected;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Places an order exactly once per idempotency key (double taps and retries
 * return the same order). Inside one transaction it re-prices the cart from
 * the menu, checks the branch is taking orders at the chosen time, checks
 * kitchen capacity, uses up stock, and writes the order with frozen lines.
 */
final class PlaceOrder
{
    public const MIN_DELAY = 5;

    public const MAX_DELAY = 240;

    public const MAX_SCHEDULE_DAYS = 7;

    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private readonly CartPricer $pricer,
        private readonly BranchSchedule $schedule,
        private readonly Capacity $capacity,
        private readonly BranchStock $stock,
        private readonly TransitionOrder $transition,
        private readonly RecordBenefits $benefits,
    ) {}

    /**
     * @throws OrderRejected
     */
    public function handle(OrderRequest $request): Order
    {
        $customer = $request->customer;
        $branch = $request->branch;

        if ($existing = $this->existing($customer, $request->idempotencyKey)) {
            return $existing;
        }

        if ($customer->restaurant_id !== $branch->restaurant_id) {
            throw OrderRejected::because('branch');
        }

        $branch->loadMissing('restaurant');

        if (! $branch->restaurant->isOperational()) {
            throw OrderRejected::because('restaurant_paused');
        }

        if ($request->lines === []) {
            throw OrderRejected::because('empty');
        }

        $prepareAt = $this->prepareAt($branch, $request);

        try {
            return DB::transaction(function () use ($request, $customer, $branch, $prepareAt): Order {
                // One order at a time per branch while capacity and stock are checked.
                Branch::withoutTenancy()->whereKey($branch->id)->lockForUpdate()->first();

                if ($prepareAt !== null && ! $this->capacity->hasRoom($branch, $prepareAt)) {
                    $next = $this->capacity->nextAvailable($branch, $prepareAt);
                    throw OrderRejected::because('capacity', ['time' => $next?->setTimezone($branch->restaurant->timezone)->format('H:i') ?? '—'], $next);
                }

                $cart = $this->pricer->price($branch, $request->lines, $prepareAt, new CheckoutBenefits(
                    $customer,
                    $request->benefits?->couponCode,
                    $request->benefits?->walletRewardId,
                    $request->benefits->pointsToRedeem ?? 0,
                ));

                if ($cart->isEmpty() || $cart->hasProblems()) {
                    throw OrderRejected::because('cart_changed');
                }

                // Never charge a total the guest didn't see (an offer ended, a price changed).
                if ($request->expectedTotalMinor !== null && $request->expectedTotalMinor !== $cart->totalMinor) {
                    throw OrderRejected::because('total_changed');
                }

                foreach ($cart->lines as $line) {
                    if ($line->product !== null && ! $this->stock->consume($line->product, $branch, $line->quantity)) {
                        throw OrderRejected::because('sold_out', ['item' => $line->product->name]);
                    }
                }

                $online = $request->paymentMethod->isOnline();
                $now = CarbonImmutable::now();

                $order = Order::withoutTenancy()->create([
                    'restaurant_id' => $branch->restaurant_id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'lane_id' => $this->lane($branch, $request->laneId),
                    'number' => 1000 + Sequence::next("orders:{$branch->restaurant_id}"),
                    'short_code' => $this->shortCode(),
                    'pickup_code' => str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT),
                    'status' => $online ? OrderStatus::PendingPayment : $this->startingStatus($request->timing, $prepareAt, $now),
                    'received_at' => ! $online && $this->startingStatus($request->timing, $prepareAt, $now) === OrderStatus::Received ? $now : null,
                    'payment_status' => $online ? PaymentStatus::Pending : PaymentStatus::Unpaid,
                    'payment_method' => $request->paymentMethod,
                    'currency' => $cart->currency,
                    'subtotal_minor' => $cart->subtotalMinor,
                    'discount_minor' => $cart->discountMinor,
                    'tax_minor' => $cart->taxMinor,
                    'service_fee_minor' => $cart->serviceFeeMinor,
                    'total_minor' => $cart->totalMinor,
                    'timing' => $request->timing,
                    'delay_minutes' => $request->timing === OrderTiming::Delay ? $request->delayMinutes : null,
                    'scheduled_for' => $request->timing === OrderTiming::Scheduled ? $prepareAt : null,
                    'prepare_at' => $prepareAt,
                    'ready_estimate_at' => ($prepareAt ?? $now)->addMinutes($cart->prepMinutes),
                    'prep_minutes' => $cart->prepMinutes,
                    'vehicle' => $this->vehicle($customer, $request->vehicleId),
                    'notes' => $request->notes !== null ? mb_substr(trim($request->notes), 0, 300) : null,
                    'source' => $request->source === [] ? null : $request->source,
                    'locale' => $request->locale,
                    'idempotency_key' => $request->idempotencyKey,
                    'placed_at' => $now,
                    'points_redeemed' => $cart->pointsRedeemed,
                ]);

                foreach ($cart->lines as $line) {
                    $this->item($order, $line);
                }

                $this->benefits->handle($order, $customer, $cart, $request->benefits);

                $this->transition->start($order, TransitionOrder::CUSTOMER, $customer->id);

                $customer->forceFill([
                    'orders_count' => $customer->orders_count + 1,
                    'total_spent_minor' => $customer->total_spent_minor + $cart->totalMinor,
                    'last_branch_id' => $branch->id,
                    'last_seen_at' => $now,
                ])->save();

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two identical taps raced past the first check: return the order that won.
            return $this->existing($customer, $request->idempotencyKey) ?? throw $e;
        }
    }

    /**
     * When the kitchen should start: now, after a delay, at a chosen time, or
     * null for "I'm on the way" (the kitchen waits for the guest's signal).
     *
     * @throws OrderRejected
     */
    private function prepareAt(Branch $branch, OrderRequest $request): ?CarbonImmutable
    {
        $now = CarbonImmutable::now();

        $at = match ($request->timing) {
            OrderTiming::Now, OrderTiming::OnTheWay => $now,
            OrderTiming::Delay => $now->addMinutes($this->delay($request->delayMinutes)),
            OrderTiming::Scheduled => $this->scheduled($request->scheduledFor),
        };

        $state = $this->schedule->state($branch, $at);

        if (! $state->acceptsOrders()) {
            $opens = $this->schedule->reopensAt($branch, $at);
            throw OrderRejected::because('closed', ['time' => $opens?->format('H:i') ?? '—']);
        }

        return $request->timing === OrderTiming::OnTheWay ? null : $at;
    }

    /**
     * @throws OrderRejected
     */
    private function delay(?int $minutes): int
    {
        if ($minutes === null || $minutes < self::MIN_DELAY || $minutes > self::MAX_DELAY) {
            throw OrderRejected::because('delay', ['min' => self::MIN_DELAY, 'max' => self::MAX_DELAY]);
        }

        return $minutes;
    }

    /**
     * @throws OrderRejected
     */
    private function scheduled(?CarbonImmutable $at): CarbonImmutable
    {
        if ($at === null || $at->lt(now()->addMinutes(15)) || $at->gt(now()->addDays(self::MAX_SCHEDULE_DAYS))) {
            throw OrderRejected::because('schedule', ['days' => self::MAX_SCHEDULE_DAYS]);
        }

        return $at->utc();
    }

    /**
     * Straight to the kitchen, or held: for a later start time, or until the
     * guest says "I'm on the way".
     */
    public function startingStatus(OrderTiming $timing, ?CarbonImmutable $prepareAt, CarbonImmutable $now): OrderStatus
    {
        if ($timing === OrderTiming::OnTheWay && $prepareAt === null) {
            return OrderStatus::Scheduled;
        }

        return $prepareAt !== null && $prepareAt->greaterThan($now->addMinute()) ? OrderStatus::Scheduled : OrderStatus::Received;
    }

    private function item(Order $order, PricedLine $line): void
    {
        $product = $line->product;

        OrderItem::withoutTenancy()->create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'name' => $product?->getTranslations('name') ?? [],
            'quantity' => $line->quantity,
            'unit_price_minor' => $line->unitPriceMinor,
            'options_minor' => $line->optionsMinor,
            'line_total_minor' => $line->totalMinor(),
            'options' => $line->options === [] ? null : $line->options,
            'notes' => $line->notes,
            'station' => $product?->station,
        ]);
    }

    /**
     * @return ?array<string, string|null>
     */
    private function vehicle(Customer $customer, ?int $vehicleId): ?array
    {
        $vehicle = CustomerVehicle::withoutTenancy()
            ->where('customer_id', $customer->id)
            ->when($vehicleId !== null, fn ($q) => $q->whereKey($vehicleId), fn ($q) => $q->orderByDesc('is_default'))
            ->first();

        return $vehicle?->snapshot();
    }

    private function lane(Branch $branch, ?int $laneId): ?int
    {
        if ($laneId === null) {
            return null;
        }

        return Lane::withoutTenancy()->where('branch_id', $branch->id)->where('is_active', true)->whereKey($laneId)->value('id');
    }

    private function existing(Customer $customer, string $key): ?Order
    {
        return Order::withoutTenancy()
            ->where('restaurant_id', $customer->restaurant_id)
            ->where('customer_id', $customer->id)
            ->where('idempotency_key', $key)
            ->first();
    }

    /** 6 characters with no look-alikes (no 0/O, 1/I/L). */
    private function shortCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Order::withoutTenancy()->where('short_code', $code)->exists());

        return $code;
    }
}
