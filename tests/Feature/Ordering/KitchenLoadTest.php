<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\KitchenLoad;
use App\Domain\Ordering\Models\Order;
use Illuminate\Support\Str;

function loadBranch(int $prep = 10)
{
    return app(CreateBranch::class)->handle(restaurant(), ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل'], 'avg_prep_minutes' => $prep]);
}

/** Inserts an order directly in a status, with optional received/ready times. */
function loadOrder($branch, OrderStatus $status, ?int $tookMinutes = null): void
{
    static $n = 0;
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $readyAt = now()->toImmutable()->subMinutes(5);

    Order::withoutTenancy()->create([
        'restaurant_id' => $branch->restaurant_id, 'branch_id' => $branch->id, 'customer_id' => $customer->id,
        'number' => 2000 + ++$n, 'short_code' => strtoupper(Str::random(6)), 'pickup_code' => '123456', 'status' => $status,
        'payment_method' => 'pay_at_pickup', 'currency' => 'QAR', 'subtotal_minor' => 1000, 'total_minor' => 1000,
        'prep_minutes' => 10, 'idempotency_key' => (string) Str::uuid(),
        'received_at' => $tookMinutes === null ? now() : $readyAt->subMinutes($tookMinutes),
        'ready_at' => $tookMinutes === null ? null : $readyAt,
    ]);
}

function freshLoad(): KitchenLoad
{
    return new KitchenLoad;
}

it('uses the branch time until there is enough history, then the real average', function () {
    $branch = loadBranch(10);

    expect(freshLoad()->of($branch))->toMatchArray(['queue' => 0, 'average' => null, 'estimate' => 10, 'level' => KitchenLoad::QUIET]);

    loadOrder($branch, OrderStatus::PickedUp, 14);
    loadOrder($branch, OrderStatus::PickedUp, 16);
    expect(freshLoad()->of($branch)['average'])->toBeNull();

    loadOrder($branch, OrderStatus::PickedUp, 15);
    expect(freshLoad()->of($branch))->toMatchArray(['average' => 15, 'estimate' => 15, 'level' => KitchenLoad::MODERATE]);
});

it('adds time for the queue and busy mode, and says how busy the kitchen is', function () {
    $branch = loadBranch(10);

    foreach (range(1, 5) as $_) {
        loadOrder($branch, OrderStatus::Received);
    }

    expect(freshLoad()->of($branch))->toMatchArray(['queue' => 5, 'estimate' => 13, 'level' => KitchenLoad::MODERATE]);

    foreach (range(1, 3) as $_) {
        loadOrder($branch, OrderStatus::Preparing);
    }

    expect(freshLoad()->of($branch))->toMatchArray(['queue' => 8, 'estimate' => 16, 'level' => KitchenLoad::BUSY]);

    $calm = loadBranch(10);
    $calm->forceFill(['busy_extra_minutes' => 10, 'busy_until' => now()->addHour()])->save();
    expect(freshLoad()->of($calm->fresh()))->toMatchArray(['estimate' => 20, 'level' => KitchenLoad::BUSY]);
});
