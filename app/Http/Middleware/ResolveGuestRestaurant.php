<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest pages of a restaurant: a paused or cancelled restaurant shows a
 * polite "temporarily unavailable" page, except for pages about orders the
 * guest already placed (status and receipt stay reachable).
 */
final class ResolveGuestRestaurant
{
    private const ALWAYS_OPEN = ['customer.order', 'customer.receipt', 'customer.manifest'];

    public function handle(Request $request, Closure $next): Response
    {
        $restaurant = $request->route('restaurant');

        if ($restaurant instanceof Restaurant
            && ! $restaurant->isOperational()
            && ! in_array($request->route()?->getName(), self::ALWAYS_OPEN, true)) {
            return response()->view('public.unavailable', ['restaurant' => $restaurant], 503);
        }

        return $next($request);
    }
}
