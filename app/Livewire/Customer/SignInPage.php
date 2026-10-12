<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Customers\Models\Customer;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Livewire\Customer\Concerns\SignsInWithPhone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sign in with a phone number and a one-time code, then go back to where
 * the guest was (only ever a page on this site).
 */
class SignInPage extends Component
{
    use GuestPage;
    use SignsInWithPhone;

    #[Locked]
    public string $to = '';

    public function mount(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
        $this->to = $this->safeReturn(request()->query('to'));

        if ($this->customer() !== null) {
            $this->redirect($this->to, navigate: true);
        }
    }

    public function render(): View
    {
        return $this->page('livewire.customer.sign-in', ['customer' => null], __('guest.sign_in.title'));
    }

    protected function signedIn(Customer $customer): void
    {
        $this->redirect($this->to, navigate: true);
    }

    private function safeReturn(mixed $to): string
    {
        $home = route('public.restaurant', $this->restaurant);
        $base = rtrim(url('/'), '/').'/';

        if (! is_string($to) || $to === '' || ! str_starts_with($to, $base) || str_contains($to, '\\')) {
            return $home;
        }

        return $to;
    }
}
