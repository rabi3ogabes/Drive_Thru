<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Events\StampRequestChanged;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Loyalty\Models\StampRequest;
use App\Domain\Loyalty\Stamps;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

use function Illuminate\Support\defer;

/**
 * The counter QR flow: a signed-in guest taps "Add my stamp", the request
 * waits on the branch's stamp screen, and a waiter approves it (choosing the
 * card and how many) or declines it. Stamps only ever come from a waiter,
 * within each card's daily cap, on the waiter's name.
 */
final class StampRequests
{
    /** Requests one guest may send in ten minutes. */
    public const PER_TEN_MINUTES = 4;

    public function __construct(private readonly Stamps $stamps) {}

    /**
     * @throws RewardUnavailable
     */
    public function ask(Customer $customer, Branch $branch): StampRequest
    {
        abort_unless($branch->restaurant_id === $customer->restaurant_id, 404);

        if (! $this->stamps->enabledFor($branch->restaurant) || $this->stamps->programs($branch->restaurant_id)->isEmpty()) {
            throw RewardUnavailable::because('program_off');
        }

        $key = 'stamp-request:'.$customer->id;

        if (RateLimiter::tooManyAttempts($key, self::PER_TEN_MINUTES)) {
            throw RewardUnavailable::because('too_many_requests');
        }

        RateLimiter::hit($key, 600);

        $request = DB::transaction(function () use ($customer, $branch): StampRequest {
            StampRequest::withoutTenancy()->where('customer_id', $customer->id)->where('status', StampRequest::PENDING)
                ->update(['status' => StampRequest::CANCELLED, 'updated_at' => now()]);

            return StampRequest::withoutTenancy()->create([
                'restaurant_id' => $customer->restaurant_id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'expires_at' => now()->addMinutes(StampRequest::MINUTES),
            ]);
        });

        $this->broadcast($request);

        return $request;
    }

    /**
     * Adds the stamps and closes the request. When the card's daily cap
     * leaves no room, nothing is added and the request stays open.
     *
     * @return array{program: StampProgram, added: int, completed: int}
     */
    public function approve(StampRequest $request, StampProgram $program, int $quantity, User $waiter): array
    {
        abort_unless($program->restaurant_id === $request->restaurant_id, 404);

        return DB::transaction(function () use ($request, $program, $quantity, $waiter): array {
            $request = StampRequest::withoutTenancy()->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->isWaiting(), 409);

            $customer = Customer::withoutTenancy()->findOrFail($request->customer_id);
            $result = $this->stamps->addAtCounter($customer, $program, $quantity, $waiter, 'stamp_request:'.$request->id, notify: false);

            if ($result['added'] > 0) {
                $request->forceFill([
                    'status' => StampRequest::APPROVED,
                    'added' => $result['added'],
                    'decided_by' => $waiter->id,
                    'decided_at' => now(),
                ])->save();

                $this->broadcast($request);
            }

            return $result;
        });
    }

    public function decline(StampRequest $request, User $waiter): void
    {
        $closed = StampRequest::withoutTenancy()->whereKey($request->id)->where('status', StampRequest::PENDING)
            ->update(['status' => StampRequest::DECLINED, 'decided_by' => $waiter->id, 'decided_at' => now(), 'updated_at' => now()]);

        if ($closed > 0) {
            activity()->performedOn($request)->causedBy($waiter)->event('loyalty.stamp_request_declined')
                ->withProperties(['restaurant_id' => $request->restaurant_id, 'customer_id' => $request->customer_id])->log('loyalty.stamp_request_declined');
            $this->broadcast($request);
        }
    }

    /** The guest changed their mind. */
    public function cancel(StampRequest $request): void
    {
        $closed = StampRequest::withoutTenancy()->whereKey($request->id)->where('status', StampRequest::PENDING)
            ->update(['status' => StampRequest::CANCELLED, 'updated_at' => now()]);

        if ($closed > 0) {
            $this->broadcast($request);
        }
    }

    /**
     * The branch's stamp screens refresh (no names or phones in the message).
     * Sent after the response, so a slow realtime server never holds up the
     * guest's tap; the screens also poll as a fallback.
     */
    private function broadcast(StampRequest $request): void
    {
        $event = StampRequestChanged::for($request->refresh());
        defer(fn () => rescue(fn () => broadcast($event), report: false));
    }
}
