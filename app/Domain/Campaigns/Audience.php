<?php

declare(strict_types=1);

namespace App\Domain\Campaigns;

use App\Domain\Crm\Enums\Segment;
use App\Domain\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a campaign is for: guests of the restaurant who agreed to receive
 * offers, in any of the chosen segments (or all of them). Consent is checked
 * again for each guest at the moment of sending.
 */
final class Audience
{
    /**
     * @param  list<Segment>  $segments  empty = everyone who agreed
     * @return Builder<Customer>
     */
    public function query(int $restaurantId, array $segments): Builder
    {
        return Customer::withoutTenancy()
            ->where('restaurant_id', $restaurantId)
            ->whereNotNull('offers_consent_at')
            ->when($segments !== [], fn (Builder $query) => $query->whereHas(
                'segments',
                fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('segment', array_map(fn (Segment $segment): string => $segment->value, $segments)),
            ));
    }

    /**
     * @param  list<Segment>  $segments
     */
    public function count(int $restaurantId, array $segments): int
    {
        return $this->query($restaurantId, $segments)->count();
    }
}
