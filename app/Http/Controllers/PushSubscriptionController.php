<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customers\CustomerAuth;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saves or removes this browser's push subscription for whoever is signed
 * in: a staff member, or a guest of the given restaurant.
 */
final class PushSubscriptionController
{
    public function store(Request $request, CustomerAuth $auth): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-=]+$/'],
            'keys.auth' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-=]+$/'],
            'restaurant' => ['nullable', 'string', 'max:60'],
        ]);

        $subscriber = $this->subscriber($request, $auth, $data['restaurant'] ?? null);
        abort_if($subscriber === null, 401);

        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'subscriber_type' => $subscriber->getMorphClass(),
                'subscriber_id' => $subscriber->getKey(),
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'locale' => app()->getLocale(),
            ],
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request, CustomerAuth $auth): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        $subscriber = $this->subscriber($request, $auth, $request->input('restaurant'));

        if ($subscriber !== null) {
            PushSubscription::query()->where('endpoint', $endpoint)
                ->where('subscriber_type', $subscriber->getMorphClass())
                ->where('subscriber_id', $subscriber->getKey())
                ->delete();
        }

        return response()->json(['subscribed' => false]);
    }

    private function subscriber(Request $request, CustomerAuth $auth, mixed $restaurantSlug): ?Model
    {
        if (is_string($restaurantSlug) && $restaurantSlug !== '') {
            $restaurant = Restaurant::query()->where('slug', $restaurantSlug)->first();

            return $restaurant === null ? null : $auth->current($restaurant);
        }

        $user = $request->user();

        return $user instanceof User && $user->is_active ? $user : null;
    }
}
