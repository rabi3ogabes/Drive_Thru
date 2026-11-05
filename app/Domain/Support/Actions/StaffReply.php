<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Staff answer a ticket: the reply joins the conversation on the guest's
 * order page and goes to their phone (WhatsApp utility template, then SMS).
 */
final class StaffReply
{
    /** Meta allows about 1,024 characters per template parameter; keep room for the rest. */
    public const WHATSAPP_LENGTH = 900;

    public function __construct(private readonly Messenger $messenger) {}

    /**
     * @throws TicketRefused
     */
    public function handle(SupportTicket $ticket, User $by, string $body): TicketMessage
    {
        $body = trim($body);

        match (true) {
            ! $by->hasRestaurantPermission($ticket->restaurant_id, RestaurantPermission::HandleSupport) => throw new TicketRefused('forbidden'),
            ! $ticket->isOpen() => throw new TicketRefused('closed'),
            $body === '' => throw new TicketRefused('invalid'),
            default => null,
        };

        $message = $this->post($ticket, $by, mb_substr($body, 0, TicketMessage::LENGTH));

        $ticket->forceFill([
            'status' => SupportTicket::ANSWERED,
            'first_reply_at' => $ticket->first_reply_at ?? now(),
        ])->save();

        activity()->performedOn($ticket)->causedBy($by)->event('support.replied')
            ->withProperties(['restaurant_id' => $ticket->restaurant_id])->log('support.replied');

        return $message;
    }

    /** Stores a staff line and sends it to the guest. */
    public function post(SupportTicket $ticket, User $by, string $body, ?string $sent = null): TicketMessage
    {
        $message = TicketMessage::withoutTenancy()->create([
            'restaurant_id' => $ticket->restaurant_id,
            'support_ticket_id' => $ticket->id,
            'author' => TicketMessage::STAFF,
            'user_id' => $by->id,
            'body' => $body,
            'created_at' => now(),
        ]);

        $message->forceFill(['message_delivery_id' => $this->notify($ticket, $sent ?? $body)?->id])->save();

        return $message;
    }

    /** Sends a line to the guest's phone without adding it to the conversation. */
    public function notify(SupportTicket $ticket, string $text): ?MessageDelivery
    {
        return $this->messenger->send(self::message($ticket->loadMissing(['order.restaurant', 'customer']), $text));
    }

    /** What the guest receives: restaurant, ticket number, the reply and a link back to the conversation. */
    public static function message(SupportTicket $ticket, string $body): Message
    {
        $order = $ticket->order;
        $locale = in_array($order->locale, ['ar', 'en'], true) ? $order->locale : ($ticket->customer->locale ?? 'ar');
        $restaurant = (string) $order->restaurant->getTranslation('name', $locale);
        $url = route('customer.order', ['restaurant' => $order->restaurant->slug, 'order' => $order->short_code]).'#support';
        $text = Str::limit(trim($body), self::WHATSAPP_LENGTH - 1, '…'); // the ellipsis counts too

        return new Message(
            phone: $ticket->customer->phone,
            purpose: Message::SUPPORT,
            text: (string) __('support.message', ['restaurant' => $restaurant, 'number' => $ticket->displayNumber(), 'reply' => $text, 'url' => $url], $locale),
            parameters: [$restaurant, $ticket->displayNumber(), $text, $url],
            locale: $locale,
            restaurantId: $ticket->restaurant_id,
            subject: $ticket,
        );
    }
}
