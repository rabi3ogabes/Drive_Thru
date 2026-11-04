<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Branches\QrCodes;
use App\Domain\Feedback\Actions\RateOrder;
use App\Domain\Feedback\Exceptions\RatingRefused;
use App\Domain\Feedback\Models\OrderRating;
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
use App\Domain\Support\Actions\GuestReply;
use App\Domain\Support\Actions\OpenTicket;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
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
 * way" and order the same again, and once it is picked up, rate it or report
 * a problem and follow the conversation. Anyone else with the link sees
 * progress only.
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

    /** The "report a problem" form, pre-filled after a low rating that names a missing or wrong item. */
    public ?string $reportCategory = null;

    public function mount(Restaurant $restaurant, Order $order): void
    {
        $this->restaurant = $restaurant;
        abort_unless($order->restaurant_id === $restaurant->id, 404);

        $this->orderId = $order->id;
        $this->justPlaced = request()->boolean('placed') && $this->isOwner($order);
        $this->reportCategory = $this->suggestedCategory($order);
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

    /**
     * The guest's rating, from the card shown after pickup.
     *
     * @param  array<int, mixed>  $tags
     */
    public function rate(int $stars, array $tags = [], ?string $comment = null): void
    {
        try {
            app(RateOrder::class)->handle($this->ownOrder(), $this->customer() ?? abort(403), $stars, $tags, $comment);
        } catch (RatingRefused $refused) {
            $this->toast($refused->getMessage(), 'error');
        }
    }

    /**
     * @param  array<int, mixed>  $items  order lines that were missing or wrong
     */
    public function reportProblem(string $category, array $items = [], string $body = ''): void
    {
        $category = TicketCategory::tryFrom($category);

        try {
            if ($category === null) {
                throw new TicketRefused('invalid');
            }

            app(OpenTicket::class)->handle($this->ownOrder(), $this->customer() ?? abort(403), $category, $items, $body);
        } catch (TicketRefused $refused) {
            $this->toast($refused->getMessage(), 'error');

            return;
        }

        $this->reportCategory = null;
        $this->dispatch('ticket-opened');
        $this->toast(__('support.guest.sent'));
    }

    public function replyToTicket(string $body): void
    {
        $ticket = $this->ticket($this->ownOrder()) ?? abort(404);

        try {
            app(GuestReply::class)->handle($ticket, $this->customer() ?? abort(403), $body);
        } catch (TicketRefused $refused) {
            $this->toast($refused->getMessage(), 'error');

            return;
        }

        $this->dispatch('ticket-replied');
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
            'rating' => $owner ? OrderRating::withoutTenancy()->where('order_id', $order->id)->first() : null,
            'canRate' => $owner && app(RateOrder::class)->canRate($order, $this->customer()),
            'ticket' => $owner ? $this->ticket($order)?->load(['messages', 'refund', 'walletReward.reward']) : null,
            'canReport' => $owner && app(OpenTicket::class)->canOpen($order, $this->customer()),
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

    /** The latest report on this order, open or resolved. */
    private function ticket(Order $order): ?SupportTicket
    {
        return SupportTicket::withoutTenancy()->where('order_id', $order->id)->latest('id')->first();
    }

    private function suggestedCategory(Order $order): ?string
    {
        if (! $this->isOwner($order) || $this->ticket($order) !== null) {
            return null;
        }

        $tags = OrderRating::withoutTenancy()->where('order_id', $order->id)->where('stars', '<=', 3)->value('tags');
        $tags = is_string($tags) ? (array) json_decode($tags, true) : (array) $tags;

        return match (true) {
            in_array('missing_item', $tags, true) => TicketCategory::MissingItem->value,
            in_array('wrong_order', $tags, true) => TicketCategory::WrongItem->value,
            default => null,
        };
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
