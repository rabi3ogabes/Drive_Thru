<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Branches\QrCodes;
use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\MarkArrived;
use App\Domain\Ordering\Actions\MarkOnTheWay;
use App\Domain\Ordering\Actions\Reorder;
use App\Domain\Ordering\Actions\TrackGuestLocation;
use App\Domain\Ordering\Enums\OrderStatus as Status;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus as PaymentState;
use App\Domain\Ordering\Exceptions\OrderRejected;
use App\Domain\Ordering\Models\Order;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Payments\GatewayResolver;
use App\Domain\Tenancy\Models\Restaurant;
use App\Http\Controllers\RealtimeAuthController;
use App\Livewire\Customer\Concerns\GuestPage;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The live order: progress, ready time, pickup code and QR. The guest who
 * placed it can also pay again, cancel within the window, say "I'm on the
 * way" and order the same again. Anyone else with the link sees progress only.
 */
class OrderStatus extends Component
{
    use GuestPage;

    #[Locked]
    public int $orderId;

    #[Locked]
    public bool $justPlaced = false;

    /** The status shown last time, to announce changes (chime, vibration, Ready alert). */
    #[Locked]
    public ?string $shownStatus = null;

    public function mount(Restaurant $restaurant, Order $order): void
    {
        $this->restaurant = $restaurant;
        abort_unless($order->restaurant_id === $restaurant->id, 404);

        $this->orderId = $order->id;
        $this->justPlaced = request()->boolean('placed') && $this->isOwner($order);
    }

    public function cancel(): void
    {
        $order = $this->ownOrder();

        try {
            app(CancelOrder::class)->byCustomer($order);
        } catch (OrderRejected $rejected) {
            $this->toast($rejected->getMessage(), 'error');

            return;
        }

        $this->toast(__('guest.order.cancelled'));
    }

    public function onTheWay(): void
    {
        app(MarkOnTheWay::class)->handle($this->ownOrder());
        $this->toast(__('guest.order.on_the_way_sent'));
    }

    /** "I'm here", optionally with the lane or spot the guest is in. */
    public function imHere(?int $laneId = null): void
    {
        app(MarkArrived::class)->handle($this->ownOrder(), MarkArrived::GUEST, $laneId);
        $this->toast(__('guest.order.arrived_sent'));
    }

    /**
     * A location ping from the guest's phone (only after they turned sharing on).
     *
     * @return array{distance_m: ?int, eta_minutes: ?int, arrived: bool}
     */
    public function ping(float $latitude, float $longitude): array
    {
        return app(TrackGuestLocation::class)->ping($this->ownOrder(), $latitude, $longitude);
    }

    public function locationDenied(): void
    {
        app(TrackGuestLocation::class)->denied($this->ownOrder());
    }

    public function pay(string $method): void
    {
        $order = $this->ownOrder();
        validator(['method' => $method], ['method' => ['required', Rule::enum(PaymentMethod::class)]])->validate();
        $method = PaymentMethod::from($method);

        $offered = app(GatewayResolver::class)->for($this->restaurant)?->methods() ?? [];

        if ($order->status !== Status::PendingPayment || ! in_array($method, $offered, true)) {
            return;
        }

        $redirect = app(StartPayment::class)->handle($order, $method);

        if ($redirect !== null) {
            $this->redirect($redirect);
        }
    }

    public function orderAgain(): void
    {
        $order = $this->ownOrder();
        $this->cart()->replace($this->restaurant->id, app(Reorder::class)->lines($order));
        $this->redirectRoute('customer.checkout', ['restaurant' => $this->restaurant->slug, 'branch' => $order->branch->code], navigate: true);
    }

    public function render(): View
    {
        $order = $this->order();
        $owner = $this->isOwner($order);
        $tz = $this->restaurant->timezone;

        if ($this->shownStatus !== null && $this->shownStatus !== $order->status->value) {
            $this->dispatch('order-status', status: $order->status->value, label: $order->status->getLabel());
        }
        $this->shownStatus = $order->status->value;

        // The "order placed" celebration plays once, not on every live refresh.
        $celebrate = $this->justPlaced;
        $this->justPlaced = false;
        $shareUrl = route('short.order', $order->short_code);

        return $this->page('livewire.customer.order-status', [
            'order' => $order,
            'celebrate' => $celebrate,
            'owner' => $owner,
            'customer' => $this->customer(),
            'tz' => $tz,
            'shareUrl' => $shareUrl,
            'qr' => $owner && ! $order->status->isFinal() ? app(QrCodes::class)->svg($order->short_code.'-'.$order->pickup_code) : null,
            'canCancel' => $owner && app(CancelOrder::class)->canCustomerCancel($order),
            'waiting' => $order->timing === OrderTiming::OnTheWay && $order->prepare_at === null && ! $order->status->isFinal(),
            'paymentFailed' => $order->payment_status === PaymentState::Failed,
            'onlineMethods' => app(GatewayResolver::class)->for($this->restaurant)?->methods() ?? [],
            'steps' => [Status::Received, Status::Preparing, Status::Ready, Status::PickedUp],
            // Live updates for the guest who ordered, or a browser opened through a signed staff link.
            'live' => $owner || in_array($order->id, (array) session(RealtimeAuthController::STAFF_VIEW_SESSION, []), true),
            'lanes' => $order->branch->lanes()->where('is_active', true)->orderBy('sort')->get(),
            'tracking' => $owner && $order->branch->tracking_enabled && $order->branch->hasLocation()
                && $order->arrived_at === null && ! $order->status->isFinal() && $order->status !== Status::PendingPayment,
        ], __('guest.order.title', ['number' => $order->displayNumber()]));
    }

    private function order(): Order
    {
        return once(fn (): Order => Order::withoutTenancy()
            ->with(['items', 'adjustments', 'branch.restaurant', 'payment', 'history', 'lane'])
            ->where('restaurant_id', $this->restaurant->id)
            ->findOrFail($this->orderId));
    }

    private function isOwner(Order $order): bool
    {
        return $this->customer()?->id === $order->customer_id;
    }

    private function ownOrder(): Order
    {
        $order = $this->order();
        abort_unless($this->isOwner($order), 403);

        return $order;
    }
}
