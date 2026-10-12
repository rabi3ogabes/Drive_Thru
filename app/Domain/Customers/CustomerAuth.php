<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Who the guest is at a restaurant. Each restaurant signs guests in
 * separately (a session token per restaurant), so being signed in at one
 * restaurant reveals nothing to another. Tokens are hashed and revocable.
 */
final class CustomerAuth
{
    /** @var array<int, Customer|null> */
    private array $resolved = [];

    public function __construct(private readonly Request $request) {}

    public function current(Restaurant $restaurant): ?Customer
    {
        if (array_key_exists($restaurant->id, $this->resolved)) {
            return $this->resolved[$restaurant->id];
        }

        $token = $this->session()->get($this->key($restaurant));
        $customer = null;

        if (is_string($token)) {
            $session = CustomerSession::withoutTenancy()
                ->with('customer')
                ->where('restaurant_id', $restaurant->id)
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('revoked_at')
                ->first();

            if ($session !== null) {
                $customer = $session->customer;

                if ($session->last_active_at === null || $session->last_active_at->lt(now()->subMinutes(5))) {
                    $session->forceFill(['last_active_at' => now()])->saveQuietly();
                }
            } else {
                $this->session()->forget($this->key($restaurant));
            }
        }

        return $this->resolved[$restaurant->id] = $customer;
    }

    public function login(Restaurant $restaurant, Customer $customer): void
    {
        $token = Str::random(64);

        CustomerSession::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'token_hash' => hash('sha256', $token),
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
            'last_active_at' => now(),
        ]);

        // A fresh session id on sign-in prevents session fixation.
        $this->session()->migrate(true);
        $this->session()->put($this->key($restaurant), $token);
        $this->resolved[$restaurant->id] = $customer;
    }

    public function logout(Restaurant $restaurant): void
    {
        $token = $this->session()->pull($this->key($restaurant));

        if (is_string($token)) {
            CustomerSession::withoutTenancy()
                ->where('token_hash', hash('sha256', $token))
                ->update(['revoked_at' => now()]);
        }

        $this->resolved[$restaurant->id] = null;
    }

    /** The current device's session row, to mark "this device" in the list. */
    public function currentSessionHash(Restaurant $restaurant): ?string
    {
        $token = $this->session()->get($this->key($restaurant));

        return is_string($token) ? hash('sha256', $token) : null;
    }

    /** The session store itself, so this also works outside a full HTTP request. */
    private function session(): Session
    {
        return app('session.store');
    }

    private function key(Restaurant $restaurant): string
    {
        return "customer.{$restaurant->id}";
    }
}
