<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketMessage;

/**
 * The guest answers in the conversation on their order page. The ticket
 * goes back to "open" so the branch sees there is something new to read.
 */
final class GuestReply
{
    /**
     * @throws TicketRefused
     */
    public function handle(SupportTicket $ticket, Customer $customer, string $body): TicketMessage
    {
        $body = trim($body);

        return match (true) {
            $ticket->customer_id !== $customer->id => throw new TicketRefused('not_yours'),
            ! $ticket->isOpen() => throw new TicketRefused('closed'),
            $body === '' => throw new TicketRefused('invalid'),
            default => $this->reply($ticket, $body),
        };
    }

    private function reply(SupportTicket $ticket, string $body): TicketMessage
    {
        $message = TicketMessage::withoutTenancy()->create([
            'restaurant_id' => $ticket->restaurant_id,
            'support_ticket_id' => $ticket->id,
            'author' => TicketMessage::GUEST,
            'body' => mb_substr($body, 0, TicketMessage::LENGTH),
            'created_at' => now(),
        ]);

        $ticket->forceFill(['status' => SupportTicket::OPEN])->save();

        return $message;
    }
}
