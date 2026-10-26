<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\ClaimReward;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Stamps;
use App\Domain\Loyalty\Tiers;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Livewire\Customer\Concerns\SignsInWithPhone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * The guest's rewards: stamp cards filling up, points (balance, tier and
 * progress, the catalog to claim from, every movement) and the wallet.
 * Shown when the restaurant has Rewards and runs points or stamp cards.
 */
class Rewards extends Component
{
    use GuestPage;
    use SignsInWithPhone;

    public function mount(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
        abort_unless($this->program() !== null || $this->stampPrograms()->isNotEmpty(), 404);
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
