<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Crm\Consent;
use App\Domain\Crm\UnsubscribeLink;
use App\Domain\Customers\Models\Customer;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The "stop offers" link from a marketing message. It works without
 * signing in, and only stops offers after a tap: link previews in chat
 * apps open the page too, and must not unsubscribe anyone.
 */
class Unsubscribe extends Component
{
    use GuestPage;

    #[Locked]
    public string $token;

    #[Locked]
    public bool $done = false;

    public function mount(Restaurant $restaurant, string $token): void
    {
        $this->restaurant = $restaurant;
        $this->token = $token;
    }

    public function stop(): void
    {
        $guest = $this->guest();

        if ($guest === null) {
            return;
        }

        app(Consent::class)->withdraw($guest, 'unsubscribe_link');
        $this->done = true;
    }

    public function render(): View
    {
        $guest = $this->guest();

        return $this->page('livewire.customer.unsubscribe', [
            'state' => match (true) {
                $guest === null => 'invalid',
                $this->done => 'done',
                ! $guest->acceptsOffers() => 'already',
                default => 'ask',
            },
        ], __('campaigns.unsubscribe.confirm'));
    }

    private function guest(): ?Customer
    {
        return UnsubscribeLink::resolve($this->restaurant, $this->token);
    }
}
