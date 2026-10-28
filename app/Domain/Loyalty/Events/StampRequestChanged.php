<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Events;

use App\Domain\Loyalty\Models\StampRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A stamp request arrived or was answered: the branch's stamp screens
 * refresh. It rides the counter channel the stamp screen already listens to;
 * ids and states only, never names or phones.
 */
final class StampRequestChanged implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $branchId,
        public readonly string $status,
    ) {}

    public static function for(StampRequest $request): self
    {
        return new self($request->id, $request->branch_id, $request->status);
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("branch.{$this->branchId}.pager")];
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
        return ['id' => $this->requestId, 'status' => $this->status, 'what' => 'stamp_request', 'at' => now()->toIso8601String()];
    }
}
