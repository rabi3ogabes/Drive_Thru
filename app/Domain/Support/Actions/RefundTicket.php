<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Actions\RefundPayment;
use App\Domain\Payments\Models\Refund;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Models\User;
use InvalidArgumentException;

/**
 * Puts a ticket right with money: all or part of what is still refundable
 * on the order's online payment, through RefundPayment (which locks the
 * payment, so two refunds can never pass together). Cash is settled at the
 * counter, so cash orders get a reward instead (RewardTicket).
 */
final class RefundTicket
{
    public function __construct(private readonly RefundPayment $refunds) {}

    /**
     * @param  ?int  $amountMinor  null = everything still refundable
     *
     * @throws TicketRefused
     */
    public function handle(SupportTicket $ticket, User $by, ?int $amountMinor): Refund
    {
        if (! $by->hasRestaurantPermission($ticket->restaurant_id, RestaurantPermission::HandleSupport)
            || ! $by->hasRestaurantPermission($ticket->restaurant_id, RestaurantPermission::RefundPayments)) {
            throw new TicketRefused('forbidden');
        }

        $payment = $this->payment($ticket);

        if ($payment === null || ! $payment->isOnline()) {
            throw new TicketRefused('not_online');
        }

        $left = $payment->refundableMinor();

        match (true) {
            $left <= 0 => throw new TicketRefused('nothing_to_refund'),
            $amountMinor !== null && $amountMinor <= 0 => throw new TicketRefused('invalid'),
            $amountMinor !== null && $amountMinor > $left => throw new TicketRefused('too_much'),
            default => null,
        };

        try {
            $refund = $this->refunds->handle($payment, $amountMinor, 'Support '.$ticket->displayNumber(), 'user:'.$by->id);
        } catch (InvalidArgumentException) {
            throw new TicketRefused('refund_failed');
        }

        if ($refund->status === Refund::FAILED) {
            throw new TicketRefused('refund_failed');
        }

        $ticket->forceFill(['outcome' => 'refund', 'refund_id' => $refund->id])->save();

        activity()->performedOn($ticket)->causedBy($by)->event('support.refunded')
            ->withProperties(['restaurant_id' => $ticket->restaurant_id, 'refund_id' => $refund->id, 'amount_minor' => $refund->amount_minor])
            ->log('support.refunded');

        return $refund;
    }

    /** What can still be refunded on this ticket's order, or null when it was not paid online. */
    public function refundable(SupportTicket $ticket): ?int
    {
        $payment = $this->payment($ticket);

        return $payment !== null && $payment->isOnline() ? $payment->refundableMinor() : null;
    }

    private function payment(SupportTicket $ticket): ?Payment
    {
        return Payment::withoutTenancy()->where('order_id', $ticket->order_id)->latest('id')->first();
    }
}
