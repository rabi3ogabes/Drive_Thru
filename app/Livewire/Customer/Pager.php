<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\ReadyCall\Models\PagerTicket;
use App\Domain\ReadyCall\Pager as ReadyPager;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The guest's own pager: a calm full-screen page that keeps the phone
 * awake, then rings, vibrates and turns bright when the counter calls the
 * number. The link is the key to this one ticket and nothing else.
 */
class Pager extends Component
{
    use GuestPage;

    #[Locked]
    public string $token;

    /** Read by the ringer in the browser: it rings when either changes. */
    #[Locked]
    public string $status = 'waiting';

    #[Locked]
    public int $calls = 0;

    public function mount(Restaurant $restaurant, string $token): void
    {
        $this->restaurant = $restaurant;
        $this->token = $token;

        $ticket = $this->ticket();
        app(ReadyPager::class)->opened($ticket);
    }

    public function render(): View
    {
        $ticket = $this->ticket();
        $live = $ticket->isLive();
        $this->status = $live || ! $ticket->status->isOpen() ? $ticket->status->value : 'expired';
        $this->calls = $ticket->calls;

        return $this->page('livewire.customer.pager', [
            'ticket' => $ticket,
            'live' => $live,
        ], __('pager.guest.title', ['number' => $ticket->number]));
    }

    private function ticket(): PagerTicket
    {
        $ticket = PagerTicket::findLive($this->token);
        abort_if($ticket === null || $ticket->restaurant_id !== $this->restaurant->id, 404);

        return $ticket;
    }
}
