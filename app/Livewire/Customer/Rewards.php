<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\ClaimReward;
use App\Domain\Loyalty\Actions\StampRequests;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Loyalty\Models\StampRequest;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Stamps;
use App\Domain\Loyalty\Tiers;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Livewire\Customer\Concerns\SignsInWithPhone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The guest's rewards: stamp cards filling up, points (balance, tier and
 * progress, the catalog to claim from, every movement) and the wallet.
 * Shown when the restaurant has Rewards and runs points or stamp cards.
 *
 * Opened from a branch's counter QR (?at=branch), it also lets the guest
 * ask for their stamp: the request waits on the branch's stamp screen and
 * this page follows it until the waiter answers.
 */
class Rewards extends Component
{
    use GuestPage;
    use SignsInWithPhone;

    /** How long the counter QR keeps the "Add my stamp" button. */
    private const COUNTER_MINUTES = 120;

    #[Locked]
    public ?int $stampRequestId = null;

    public function mount(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
        abort_unless($this->program() !== null || $this->stampPrograms()->isNotEmpty(), 404);

        $code = request()->query('at');

        if (is_string($code) && $code !== '' && strlen($code) <= 60) {
            $branch = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('code', $code)->first();

            if ($branch !== null) {
                session()->put($this->counterKey(), ['branch' => $branch->id, 'until' => now()->addMinutes(self::COUNTER_MINUTES)->getTimestamp()]);
            }
        }

        $customer = $this->customer();
        $this->stampRequestId = $customer === null ? null : StampRequest::withoutTenancy()->where('customer_id', $customer->id)->waiting()->latest('id')->value('id');
    }

    /** "Add my stamp": sends the request to the counter the guest scanned. */
    public function askStamp(): void
    {
        $customer = $this->customer();
        $branch = $this->counterBranch();

        if ($customer === null || $branch === null) {
            $this->toast(__('guest.rewards.counter.scan_first'), 'error');

            return;
        }

        try {
            $this->stampRequestId = app(StampRequests::class)->ask($customer, $branch)->id;
        } catch (RewardUnavailable $problem) {
            $this->toast($problem->getMessage(), 'error');
        }
    }

    public function cancelStamp(): void
    {
        $request = $this->stampRequest();

        if ($request !== null) {
            app(StampRequests::class)->cancel($request);
        }

        $this->stampRequestId = null;
    }

    /** Polled while the request waits: tells the guest how it ended, once. */
    public function checkStamp(): void
    {
        $request = $this->stampRequest();

        if ($request === null || $request->isWaiting()) {
            return;
        }

        $this->stampRequestId = null;

        if ($request->status === StampRequest::PENDING) {
            $request->forceFill(['status' => StampRequest::EXPIRED])->save();
        }

        match ($request->status) {
            StampRequest::APPROVED => $this->celebrate($request),
            StampRequest::DECLINED => $this->toast(__('guest.rewards.counter.declined'), 'error'),
            StampRequest::EXPIRED => $this->toast(__('guest.rewards.counter.expired'), 'error'),
            default => null,
        };
    }

    private function celebrate(StampRequest $request): void
    {
        $this->toast(trans_choice('guest.rewards.counter.added', $request->added, ['count' => $request->added]));
        $this->dispatch('stamp-added');
    }

    public function claim(int $rewardId): void
    {
        $customer = $this->customer();

        if ($customer === null) {
            $this->toast(__('guest.rewards.sign_in_to_claim'), 'error');

            return;
        }

        $reward = Reward::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->find($rewardId);

        try {
            abort_if($reward === null, 404);
            app(ClaimReward::class)->handle($customer, $reward);
        } catch (RewardUnavailable $problem) {
            $this->toast($problem->getMessage(), 'error');

            return;
        }

        $this->toast(__('guest.rewards.claimed', ['reward' => $reward->name]));
    }

    public function render(): View
    {
        $customer = $this->customer();
        $program = $this->program();

        $programs = $this->stampPrograms();
        $cards = $customer === null || $programs->isEmpty() ? collect() : StampCard::withoutTenancy()
            ->where('customer_id', $customer->id)->whereIn('stamp_program_id', $programs->modelKeys())->get()->keyBy('stamp_program_id');

        return $this->page('livewire.customer.rewards', [
            'customer' => $customer,
            'counterBranch' => $programs->isEmpty() ? null : $this->counterBranch(),
            'stampRequest' => $this->stampRequest(),
            'program' => $program,
            'stampPrograms' => $programs,
            'stampCards' => $cards,
            'progress' => $customer === null ? null : app(Tiers::class)->progress($customer),
            'tiers' => app(Tiers::class)->tiers($this->restaurant->id),
            'wallet' => $customer === null ? collect() : WalletReward::withoutTenancy()->usable()->with('reward.product')->where('customer_id', $customer->id)->orderBy('expires_at')->get(),
            'past' => $customer === null ? collect() : WalletReward::withoutTenancy()->with('reward.product')->where('customer_id', $customer->id)->where('status', '!=', WalletReward::AVAILABLE)->latest('updated_at')->limit(10)->get(),
            'catalog' => Reward::withoutTenancy()
                ->with('product')
                ->where('restaurant_id', $this->restaurant->id)
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->whereNotNull('points_cost')
                ->where('type', '!=', 'bonus_points')
                ->orderBy('points_cost')
                ->get(),
            'history' => $customer === null ? collect() : LedgerEntry::withoutTenancy()->with('order')->where('customer_id', $customer->id)->latest('id')->limit(50)->get(),
        ], __('guest.rewards.title'));
    }

    protected function signedIn(Customer $customer): void
    {
        $this->toast(__('guest.sign_in.welcome', ['name' => $customer->firstName() ?? '']));
    }

    /** The branch whose counter QR the guest scanned in the last two hours. */
    private function counterBranch(): ?Branch
    {
        $counter = session()->get($this->counterKey());

        if (! is_array($counter) || (int) ($counter['until'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return Branch::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->find((int) ($counter['branch'] ?? 0));
    }

    private function counterKey(): string
    {
        return 'stamp_counter.'.$this->restaurant->id;
    }

    private function stampRequest(): ?StampRequest
    {
        $customer = $this->customer();

        if ($this->stampRequestId === null || $customer === null) {
            return null;
        }

        return StampRequest::withoutTenancy()->where('customer_id', $customer->id)->find($this->stampRequestId);
    }

    /**
     * @return Collection<int, StampProgram>
     */
    private function stampPrograms(): Collection
    {
        return once(fn (): Collection => $this->restaurant->hasFeature(PlanFeature::Loyalty)
            ? app(Stamps::class)->programs($this->restaurant->id)
            : new Collection);
    }

    private function program(): ?LoyaltyProgram
    {
        if (! $this->restaurant->hasFeature(PlanFeature::Loyalty)) {
            return null;
        }

        $program = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->first();

        return $program !== null && $program->isActive() ? $program : null;
    }
}
