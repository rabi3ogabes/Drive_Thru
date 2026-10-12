<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\Impersonation;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;

final class LeaveImpersonationController
{
    public function __invoke(Impersonation $impersonation): RedirectResponse
    {
        $restaurantId = $impersonation->restaurantId();
        $impersonator = $impersonation->stop();

        if ($impersonator === null) {
            return redirect()->to('/admin/login');
        }

        $restaurant = $restaurantId !== null ? Restaurant::query()->find($restaurantId) : null;

        return redirect()->to($restaurant !== null ? "/admin/restaurants/{$restaurant->slug}" : '/admin');
    }
}
