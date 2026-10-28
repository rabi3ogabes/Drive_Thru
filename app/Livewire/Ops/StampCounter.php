<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\StampRequests;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Loyalty\Models\StampRequest;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Stamps;
use App\Livewire\Ops\Concerns\OpsScreen;
use App\Models\User;
use App\Support\Lang;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Stamps at the till, for guests who paid at the counter. Guests who scanned
 * the counter QR and tapped "Add my stamp" wait at the top: one tap approves
 * (+1 on the only card) or opens them to choose. Otherwise type their phone,
 * see their cards and free items, add stamps (within the daily cap) and hand
 * over a free item. Every step is on the staff member's name.
 */
class StampCounter extends Component
{
    use OpsScreen;

    public string $phone = '';

    /** @var array<int, int> program id => stamps to add */
    public array $quantities = [];

    #[Locked]
    public ?int $customerId = null;

    #[Locked]
    public ?string $notice = null;

    /** The guest request being answered, when the guest was opened from the waiting list. */
    #[Locked]
    public ?int $requestId = null;

    public function mount(int $branchId, ?int $deviceId = null): void
    {
        $this->branchId = $branchId;
        $this->deviceId = $deviceId;
        abort_unless($deviceId === null && $this->operator()->can(P::CollectPayments, P::OperatePager), 403);
        abort_unless(app(Stamps::class)->enabledFor($this->branch()->restaurant), 403);
    }

    public function find(): void
    {
        $this->notice = null;
        $phone = Phone::normalize($this->phone);

        if ($phone === null) {
            $this->notice = Lang::text('ops.stamps.bad_phone');

            return;
        }

        $restaurantId = $this->branch()->restaurant_id;
        $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $restaurantId, 'phone' => $phone]);
        $this->customerId = $customer->id;
        $this->quantities = [];
    }

    public function step(int $programId, int $by): void
    {
        $this->quantities[$programId] = max(0, min(20, ($this->quantities[$programId] ?? 0) + $by));
    }

    public function add(int $programId): void
    {
        $customer = $this->customer();
        $program = StampProgram::withoutTenancy()->where('restaurant_id', $customer->restaurant_id)->findOrFail($programId);
        $quantity = (int) ($this->quantities[$programId] ?? 0);

        if ($quantity < 1) {
            return;
        }

        $request = $this->requestId === null ? null : StampRequest::withoutTenancy()->where('customer_id', $customer->id)->find($this->requestId);

        $result = $request !== null && $request->isWaiting()
            ? app(StampRequests::class)->approve($request, $program, $quantity, $this->actor())
            : app(Stamps::class)->addAtCounter($customer, $program, $quantity, $this->actor());
        $this->quantities[$programId] = 0;

        if ($result['added'] > 0) {
            $this->requestId = null;
        }

        $this->dispatch('ops-toast', ...match (true) {
            $result['added'] === 0 => ['message' => Lang::text('ops.stamps.capped'), 'tone' => 'error'],
            $result['completed'] > 0 => ['message' => Lang::text('ops.stamps.completed', ['reward' => $program->reward->name]), 'tone' => 'success'],
            default => ['message' => trans_choice('ops.stamps.added', $result['added'], ['count' => $result['added']]), 'tone' => 'success'],
        });
    }

    public function redeem(int $walletId): void
    {
        $customer = $this->customer();
        $wallet = WalletReward::withoutTenancy()->with('reward')->where('customer_id', $customer->id)->findOrFail($walletId);
        abort_unless($wallet->status === WalletReward::AVAILABLE, 409);

        app(Stamps::class)->redeemAtCounter($wallet, $this->actor());
        $this->dispatch('ops-toast', message: Lang::text('ops.stamps.redeemed', ['reward' => $wallet->reward->name]), tone: 'success');
    }

    public function clear(): void
    {
        $this->reset('phone', 'quantities', 'customerId', 'notice', 'requestId');
    }

    /** One tap: +1 stamp on the restaurant's only card. */
    public function approve(int $requestId): void
    {
        $request = $this->waitingRequest($requestId);
        $programs = app(Stamps::class)->programs($this->branch()->restaurant_id);
        $program = $programs->count() === 1 ? $programs->first() : null;

        if ($request === null || $program === null) {
            $this->open($requestId);

            return;
        }
        $result = app(StampRequests::class)->approve($request, $program, 1, $this->actor());

        $this->dispatch('ops-toast', ...match (true) {
            $result['added'] === 0 => ['message' => Lang::text('ops.stamps.capped'), 'tone' => 'error'],
            $result['completed'] > 0 => ['message' => Lang::text('ops.stamps.completed', ['reward' => $program->reward->name]), 'tone' => 'success'],
            default => ['message' => Lang::text('ops.stamps.approved', ['name' => $request->customer->name ?? Lang::text('ops.stamps.guest')]), 'tone' => 'success'],
        });
    }

    /** Opens the guest who asked, to choose the card and how many. */
    public function open(int $requestId): void
    {
        $request = $this->waitingRequest($requestId);

        if ($request === null) {
            $this->dispatch('ops-toast', message: Lang::text('ops.stamps.request_gone'), tone: 'error');

            return;
        }

        $this->reset('phone', 'quantities', 'notice');
        $this->customerId = $request->customer_id;
        $this->requestId = $request->id;

        foreach (app(Stamps::class)->programs($this->branch()->restaurant_id) as $program) {
            $this->quantities[$program->id] = 0;
        }
    }

    public function decline(int $requestId): void
    {
        $request = $this->waitingRequest($requestId);

        if ($request !== null) {
            app(StampRequests::class)->decline($request, $this->actor());
        }

        if ($this->requestId === $requestId) {
            $this->requestId = null;
        }

        $this->dispatch('ops-toast', message: Lang::text('ops.stamps.declined'), tone: 'success');
    }

    private function waitingRequest(int $requestId): ?StampRequest
    {
        abort_unless($this->operator()->can(P::CollectPayments, P::OperatePager), 403);

        return StampRequest::withoutTenancy()->with('customer')->where('branch_id', $this->branchId)->waiting()->find($requestId);
    }

    public function render(): View
    {
        $branch = $this->branch();
        $customer = $this->customerId === null ? null : Customer::withoutTenancy()->find($this->customerId);
        $stamps = app(Stamps::class);
        $programs = $stamps->programs($branch->restaurant_id);
        $cards = $customer === null ? collect() : StampCard::withoutTenancy()->where('customer_id', $customer->id)->get()->keyBy('stamp_program_id');

        $waiting = StampRequest::withoutTenancy()->with('customer')->where('branch_id', $branch->id)->waiting()->oldest('id')->limit(12)->get();
        $waitingCards = $waiting->isEmpty() ? collect() : StampCard::withoutTenancy()->whereIn('customer_id', $waiting->pluck('customer_id'))
            ->get()->groupBy('customer_id');

        return view('livewire.ops.stamp-counter', [
            'branch' => $branch,
            'waiting' => $waiting,
            'waitingCards' => $waitingCards,
            'request' => $this->requestId === null ? null : $waiting->firstWhere('id', $this->requestId),
            'customer' => $customer,
            'programs' => $programs,
            'cards' => $cards,
            'today' => $cards->map(fn (StampCard $card): int => $stamps->todayOn($card, $branch->restaurant->timezone)),
            'wallet' => $customer === null ? collect() : WalletReward::withoutTenancy()->usable()->with('reward.product')
                ->where('customer_id', $customer->id)->orderBy('expires_at')->get(),
        ]);
    }

    private function customer(): Customer
    {
        abort_unless($this->operator()->can(P::CollectPayments, P::OperatePager), 403);

        return Customer::withoutTenancy()->where('restaurant_id', $this->branch()->restaurant_id)->findOrFail($this->customerId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
