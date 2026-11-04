<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\StaffAlerts;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

/**
 * "Report a problem": the guest who picked up an order tells the branch
 * what went wrong, within 7 days. One open ticket per order; the order row
 * is locked so a double tap makes one ticket. The branch managers are told.
 */
final class OpenTicket
{
    public const WINDOW_DAYS = 7;

    public function __construct(private readonly StaffAlerts $alerts) {}

    public function canOpen(Order $order, ?Customer $customer): bool
    {
        return $customer !== null && $this->problem($order, $customer) === null;
    }

    /**
     * @param  array<int, mixed>  $itemIds  order lines the guest pointed at; others are dropped
     *
     * @throws TicketRefused
     */
    public function handle(Order $order, Customer $customer, TicketCategory $category, array $itemIds, string $body): SupportTicket
    {
        $body = trim($body);

        if ($body === '') {
            throw new TicketRefused('invalid');
        }

        $ticket = DB::transaction(function () use ($order, $customer, $category, $itemIds, $body): SupportTicket {
            $locked = Order::withoutTenancy()->with('items')->lockForUpdate()->findOrFail($order->id);

            if (($problem = $this->problem($locked, $customer)) !== null) {
                throw new TicketRefused($problem);
            }

            $number = (int) SupportTicket::withoutTenancy()->where('restaurant_id', $locked->restaurant_id)->lockForUpdate()->max('number');

            $ticket = SupportTicket::withoutTenancy()->create([
                'restaurant_id' => $locked->restaurant_id,
                'branch_id' => $locked->branch_id,
                'order_id' => $locked->id,
                'customer_id' => $customer->id,
                'number' => max(SupportTicket::FIRST_NUMBER, $number + 1),
                'category' => $category,
                'items' => $category->asksForItems()
                    ? array_values(array_intersect($locked->items->modelKeys(), array_map('intval', array_filter($itemIds, 'is_numeric'))))
                    : [],
            ]);

            TicketMessage::withoutTenancy()->create([
                'restaurant_id' => $ticket->restaurant_id,
                'support_ticket_id' => $ticket->id,
                'author' => TicketMessage::GUEST,
                'body' => mb_substr($body, 0, TicketMessage::LENGTH),
                'created_at' => now(),
            ]);

            return $ticket;
        });

        $this->alert($ticket, $order);

        return $ticket->load('messages');
    }

    private function problem(Order $order, Customer $customer): ?string
    {
        return match (true) {
            $order->customer_id !== $customer->id || $order->restaurant_id !== $customer->restaurant_id => 'not_yours',
            $order->status !== OrderStatus::PickedUp => 'not_picked_up',
            ($order->picked_up_at ?? $order->updated_at)?->lt(now()->subDays(self::WINDOW_DAYS)) ?? true => 'too_late',
            SupportTicket::withoutTenancy()->where('order_id', $order->id)->where('status', '!=', SupportTicket::RESOLVED)->exists() => 'already_open',
            default => null,
        };
    }

    private function alert(SupportTicket $ticket, Order $order): void
    {
        $order->loadMissing('branch.restaurant');

        $this->alerts->send(AlertKind::TicketOpened, $ticket->restaurant_id, $order->branch, [
            'number' => $ticket->displayNumber(),
            'order' => $order->number,
            'category' => [
                'ar' => __('support.categories.'.$ticket->category->value, [], 'ar'),
                'en' => __('support.categories.'.$ticket->category->value, [], 'en'),
            ],
            'branch' => $order->branch->getTranslations('name'),
        ], route('ops.screen', [$order->branch->restaurant, $order->branch->code, 'live']));
    }
}
