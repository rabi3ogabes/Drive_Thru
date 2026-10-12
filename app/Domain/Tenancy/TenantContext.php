<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Restaurant;
use Closure;

/**
 * The restaurant the current request or job acts for. Bound as a scoped
 * singleton, so it resets between requests, jobs and Octane workers.
 */
final class TenantContext
{
    private ?Restaurant $restaurant = null;

    public function set(?Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function get(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function id(): ?int
    {
        return $this->restaurant?->getKey();
    }

    public function check(): bool
    {
        return $this->restaurant !== null;
    }

    /**
     * Run a callback as the given restaurant, restoring the previous tenant afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Restaurant $restaurant, Closure $callback): mixed
    {
        $previous = $this->restaurant;
        $this->restaurant = $restaurant;

        try {
            return $callback();
        } finally {
            $this->restaurant = $previous;
        }
    }
}
