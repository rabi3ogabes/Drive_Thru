<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Concerns;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\CustomerAuth;
use App\Domain\Customers\Models\Customer;
use App\Domain\Ordering\Cart;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

/**
 * Shared by every guest page: the restaurant (locked, so the browser can't
 * swap it), the signed-in guest, their cart, and the page layout.
 */
trait GuestPage
{
    #[Locked]
    public Restaurant $restaurant;

    protected function customer(): ?Customer
    {
        return app(CustomerAuth::class)->current($this->restaurant);
    }

    protected function cart(): Cart
    {
        return app(Cart::class);
    }

    protected function findBranch(string $code): ?Branch
    {
        return Branch::withoutTenancy()
            ->with('restaurant')
            ->where('restaurant_id', $this->restaurant->id)
            ->where('code', $code)
            ->first();
    }

    /**
     * Remembers where the guest came from (QR lane, campaign, channel, promo)
     * for the order they place in this visit.
     */
    protected function rememberSource(): void
    {
        $source = array_filter(
            request()->only(['lane', 'campaign', 'channel', 'promo', 'source']),
            fn (mixed $v): bool => is_string($v) && $v !== '' && strlen($v) <= 60,
        );

        if ($source !== []) {
            session()->put("source.{$this->restaurant->id}", $source);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function source(): array
    {
        $source = session()->get("source.{$this->restaurant->id}", []);

        return is_array($source) ? $source : [];
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function page(string $view, array $data, ?string $title = null): View
    {
        return view($view, $data)->layout('components.layouts.customer', [
            'restaurant' => $this->restaurant,
            'title' => $title,
        ]);
    }

    protected function toast(string $message, string $tone = 'success'): void
    {
        $this->dispatch('toast', message: $message, tone: $tone);
    }
}
