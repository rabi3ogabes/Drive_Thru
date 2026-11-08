<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketMessage;
use App\Models\User;
use App\Support\Money;

/**
 * Closes a ticket. An optional closing note joins the conversation, and the
 * guest gets one last message saying how it was put right.
 */
final class ResolveTicket
{
    public function __construct(private readonly StaffReply $replies) {}

    /**
     * @throws TicketRefused
     */
    public function handle(SupportTicket $ticket, User $by, ?string $note): void
    {
        if (! $by->hasRestaurantPermission($ticket->restaurant_id, RestaurantPermission::HandleSupport)) {
            throw new TicketRefused('forbidden');
        }

        if (! $ticket->isOpen()) {
            throw new TicketRefused('closed');
        }

        $ticket->forceFill(['status' => SupportTicket::RESOLVED, 'resolved_at' => now(), 'resolved_by' => $by->id])->save();
        $ticket->loadMissing(['order.restaurant', 'customer', 'refund', 'walletReward.reward']);

        $note = trim((string) $note);
        $outcome = $this->outcome($ticket);
        // The outcome shows on the order page anyway; only a note joins the conversation.
        if ($note === '') {
            $this->replies->notify($ticket, $outcome);
        } else {
            $this->replies->post($ticket, $by, mb_substr($note, 0, TicketMessage::LENGTH), $note."\n".$outcome);
        }

        activity()->performedOn($ticket)->causedBy($by)->event('support.resolved')
            ->withProperties(['restaurant_id' => $ticket->restaurant_id, 'outcome' => $ticket->outcome])->log('support.resolved');
    }

    private function outcome(SupportTicket $ticket): string
    {
        $locale = in_array($ticket->order->locale, ['ar', 'en'], true) ? $ticket->order->locale : 'ar';

        return (string) match (true) {
            $ticket->outcome === 'refund' && $ticket->refund !== null => __('support.guest.outcome.refund', ['amount' => Money::format($ticket->refund->amount_minor, $ticket->order->currency, $locale)], $locale),
            $ticket->outcome === 'reward' && $ticket->walletReward !== null => __('support.guest.outcome.reward', ['reward' => $ticket->walletReward->reward->getTranslation('name', $locale)], $locale),
            default => __('support.guest.outcome.none', [], $locale),
        };
    }
}
