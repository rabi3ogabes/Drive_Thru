<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Ordering\Actions\Reorder;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The guest's orders here: what's cooking first, then history with "order again". */
class Orders extends Component
{
    use GuestPage;

    public int $limit = 20;

    public function mount(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function more(): void
    {
        $this->limit = min($this->limit + 20, 200);
    }

    public function orderAgain(int $orderId): void
    {
        $customer = $this->customer();
        $order = $customer === null ? null : Order::withoutTenancy()->with(['items', 'branch'])
            ->where('restaurant_id', $this->restaurant->id)
            ->where('customer_id', $customer->id)
            ->find($orderId);

        if ($order === null) {
            return;
        }

        $this->cart()->replace($this->restaurant->id, app(Reorder::class)->lines($order));
        $this->redirectRoute('customer.checkout', ['restaurant' => $this->restaurant->slug, 'branch' => $order->branch->code], navigate: true);
    }

    public function render(): View
    {
        $customer = $this->customer();
        $limit = max(1, min($this->limit, 200));
        $orders = $customer === null ? collect() : Order::withoutTenancy()
            ->with(['items', 'branch'])
            ->where('restaurant_id', $this->restaurant->id)
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit($limit + 1)
            ->get();
        $shown = $orders->take($limit);

        return $this->page('livewire.customer.orders', [
            'customer' => $customer,
            'active' => $shown->filter(fn (Order $o): bool => ! $o->status->isFinal())->values(),
            'past' => $shown->filter(fn (Order $o): bool => $o->status->isFinal())->values(),
            'hasMore' => $orders->count() > $limit,
        ], __('guest.nav.orders'));
    }
}
