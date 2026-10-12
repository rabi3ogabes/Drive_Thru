<?php

declare(strict_types=1);

namespace App\Domain\Branches;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * The public links guests use: one for the restaurant (guests choose a
 * branch there) and one locked link per branch.
 */
final class Links
{
    public function restaurant(Restaurant $restaurant): string
    {
        return route('public.restaurant', ['restaurant' => $restaurant->slug]);
    }

    public function branch(Restaurant $restaurant, Branch $branch): string
    {
        return route('public.branch', ['restaurant' => $restaurant->slug, 'branch' => $branch->code]);
    }

    /** The link a printed QR carries: the branch link, or the restaurant link. */
    public function forQr(Restaurant $restaurant, ?Branch $branch = null): string
    {
        return $branch === null ? $this->restaurant($restaurant) : $this->branch($restaurant, $branch);
    }

    /** "drivethru.qa/masar" without the scheme, for print and display. */
    public static function display(string $url): string
    {
        return (string) preg_replace('#^https?://#', '', rtrim($url, '/'));
    }
}
