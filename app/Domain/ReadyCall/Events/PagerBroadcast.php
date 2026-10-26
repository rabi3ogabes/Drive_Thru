<?php

declare(strict_types=1);

namespace App\Domain\ReadyCall\Events;

use App\Domain\ReadyCall\Models\PagerTicket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A pager ticket changed: the counter screens and the ready board of the
 * branch refresh, and the guest's own pager page rings when it says
 * "ready". Numbers and states only, never phones.
 */
final class PagerBroadcast implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $ticketId,
        public readonly int $branchId,
        public readonly string $token,
        public readonly int $number,
        public readonly string $status,
        public readonly string $what,
    ) {}

    public static function for(PagerTicket $ticket, string $what): self
    {
        return new self($ticket->id, $ticket->branch_id, $ticket->token, $ticket->number, $ticket->status->value, $what);
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->branchId}.pager"),
            new PrivateChannel("pager.{$this->token}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pager.updated';
    }

    /**
     * @return array<string, int|string>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticketId,
            'number' => $this->number,
            'status' => $this->status,
            'what' => $this->what,
            'at' => now()->toIso8601String(),
        ];
    }
}
