<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Customers\Models\Customer;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * The "stop offers" link in every marketing message. It needs no sign-in:
 * the token is the guest's id with an HMAC over the restaurant and the
 * guest, so it can't be guessed or moved to another guest or restaurant.
 */
final class UnsubscribeLink
{
    public static function url(Customer $customer, Restaurant $restaurant): string
    {
        return route('customer.unsubscribe', [$restaurant, self::token($customer)]);
    }

    /** A link of the real shape for previews; it opens the "this link doesn't work" page. */
    public static function sampleUrl(Restaurant $restaurant): string
    {
        return route('customer.unsubscribe', [$restaurant, self::encode(4821).'.'.self::signature(0, 4821)]);
    }

    public static function token(Customer $customer): string
    {
        return self::encode($customer->id).'.'.self::signature($customer->restaurant_id, $customer->id);
    }

    public static function resolve(Restaurant $restaurant, string $token): ?Customer
    {
        if (preg_match('/^([0-9a-z]{1,12})\.([A-Za-z0-9_-]{22})$/', $token, $parts) !== 1) {
            return null;
        }

        $id = (int) base_convert($parts[1], 36, 10);

        if ($id < 1 || ! hash_equals(self::signature($restaurant->id, $id), $parts[2])) {
            return null;
        }

        return Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->find($id);
    }

    private static function encode(int $id): string
    {
        return base_convert((string) $id, 10, 36);
    }

    private static function signature(int $restaurantId, int $customerId): string
    {
        $mac = hash_hmac('sha256', 'unsubscribe|'.$restaurantId.'|'.$customerId, (string) config('app.key'), true);

        return rtrim(strtr(base64_encode(substr($mac, 0, 16)), '+/', '-_'), '=');
    }
}
