<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Customers\Models\Customer;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Referrals\Referrals;
use App\Domain\Referrals\ReferralSettings;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Livewire\Customer\Concerns\SignsInWithPhone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A friend's invite link (/{restaurant}/invite/{code}): who invited them,
 * their welcome gift, and sign-in right here. The invite is remembered for
 * this browser, so signing in later (at checkout, say) still counts.
 */
class Invite extends Component
{
    use GuestPage;
    use SignsInWithPhone;

    /** welcome | joined | own | member | invalid */
    #[Locked]
    public string $state = 'invalid';

    #[Locked]
    public ?int $referrerId = null;

    public function mount(Restaurant $restaurant, string $code): void
    {
        $this->restaurant = $restaurant;
        $referrer = ReferralSettings::for($restaurant)->isLive() ? app(Referrals::class)->referrer($restaurant, $code) : null;

        if ($referrer === null) {
            return;
        }

        $this->referrerId = $referrer->id;
        $customer = $this->customer();

        $this->state = match (true) {
            $customer === null => 'welcome',
            $customer->id === $referrer->id => 'own',
            default => 'member',
        };

        if ($this->state === 'welcome') {
            app(Referrals::class)->remember($restaurant, $referrer);
        }
    }

    public function render(): View
    {
        $settings = ReferralSettings::for($this->restaurant);
        $referrer = $this->referrerId === null ? null : Customer::withoutTenancy()->find($this->referrerId);

        return $this->page('livewire.customer.invite', [
            'customer' => $this->customer(),
            'referrer' => $referrer,
            'gift' => $this->state === 'invalid' ? null : $settings->friendReward(),
        ], __('referrals.invite.page_title'));
    }

    protected function signedIn(Customer $customer): void
    {
        $referral = Referral::withoutTenancy()->where('friend_id', $customer->id)->first();

        $this->state = match (true) {
            $customer->id === $this->referrerId => 'own',
            $referral !== null && $referral->status === Referral::PENDING => 'joined',
            default => 'member',
        };
    }
}
