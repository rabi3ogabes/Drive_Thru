<?php

use App\Domain\Crm\Enums\Segment;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Crm\Segments;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00')));

function segmentGuest(Restaurant $restaurant, string $phone): Customer
{
    return Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => $phone]);
}

/** @return list<string> */
function segmentsOf(Customer $guest): array
{
    return CustomerSegment::withoutTenancy()->where('customer_id', $guest->id)->orderBy('segment')->pluck('segment')
        ->map(fn ($s) => $s instanceof Segment ? $s->value : $s)->unique()->values()->all();
}

it('puts every guest in the right segments', function () {
    $restaurant = restaurant();
    $newbie = segmentGuest($restaurant, '+97455550001');
    pastOrder($newbie, 3000, now()->subDays(5));

    $regular = segmentGuest($restaurant, '+97455550002');
    foreach ([100, 60, 20, 10] as $days) {
        pastOrder($regular, 3000, now()->subDays($days));
    }

    $atRisk = segmentGuest($restaurant, '+97455550003');
    pastOrder($atRisk, 3000, now()->subDays(80));
    pastOrder($atRisk, 3000, now()->subDays(45));

    $gone = segmentGuest($restaurant, '+97455550004');
    pastOrder($gone, 3000, now()->subDays(200));
    pastOrder($gone, 3000, now()->subDays(120));

    $bigSpender = segmentGuest($restaurant, '+97455550005');
    pastOrder($bigSpender, 30000, now()->subDays(25));

    $counts = app(Segments::class)->compute($restaurant);

    expect(segmentsOf($newbie))->toBe(['new'])
        ->and(segmentsOf($regular))->toBe(['regular'])
        ->and(segmentsOf($atRisk))->toBe(['at_risk'])
        ->and(segmentsOf($gone))->toBe(['inactive'])
        ->and(segmentsOf($bigSpender))->toBe(['high_spender', 'new'])
        ->and($counts)->toMatchArray(['new' => 2, 'regular' => 1, 'vip' => 0, 'high_spender' => 1, 'at_risk' => 1, 'inactive' => 1]);
});

it('makes the top loyalty tier VIP whatever they spent', function () {
    $restaurant = restaurant();
    Tier::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Bronze', 'ar' => 'برونزي'], 'threshold_points' => 0]);
    $gold = Tier::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Gold', 'ar' => 'ذهبي'], 'threshold_points' => 5000]);
    $guest = segmentGuest($restaurant, '+97455550010');
    $guest->forceFill(['tier_id' => $gold->id])->save();
    pastOrder($guest, 1000, now()->subDays(40));
    foreach (range(1, 30) as $i) {
        pastOrder(segmentGuest($restaurant, '+9745556'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)), 5000, now()->subDays(40));
    }

    app(Segments::class)->compute($restaurant);

    expect(segmentsOf($guest))->toContain('vip');
});

it('finds guests who keep buying the same best seller', function () {
    $restaurant = restaurant();
    $karak = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $other = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $fan = segmentGuest($restaurant, '+97455550020');
    $casual = segmentGuest($restaurant, '+97455550021');

    foreach ([3, 9, 15] as $days) {
        pastOrder($fan, 300, now()->subDays($days), 'picked_up', [$karak->id]);
    }
    pastOrder($casual, 300, now()->subDays(4), 'picked_up', [$karak->id, $other->id]);
    pastOrder($fan, 300, now()->subDays(200), 'picked_up', [$other->id]);

    app(Segments::class)->compute($restaurant);

    expect(CustomerSegment::withoutTenancy()->where('segment', 'frequent_buyer')->get(['customer_id', 'product_id'])->toArray())
        ->toBe([['customer_id' => $fan->id, 'product_id' => $karak->id]]);
});

it('gives a restaurant with no orders empty segments without failing', function () {
    $restaurant = restaurant();
    segmentGuest($restaurant, '+97455550030');

    $counts = app(Segments::class)->compute($restaurant);

    expect(array_sum($counts))->toBe(0)
        ->and(CustomerSegment::withoutTenancy()->count())->toBe(0);
});

it('takes guests out of a segment once they stop qualifying', function () {
    $restaurant = restaurant();
    $guest = segmentGuest($restaurant, '+97455550040');
    pastOrder($guest, 3000, now()->subDays(5));
    app(Segments::class)->compute($restaurant);
    expect(segmentsOf($guest))->toBe(['new']);

    $this->travelTo(now()->addDays(90));
    app(Segments::class)->compute($restaurant);

    expect(segmentsOf($guest))->toBe(['inactive']);
});

it('keeps each restaurant’s segments to itself', function () {
    $one = restaurant(slug: 'one');
    $two = restaurant(['en' => 'Two', 'ar' => 'اثنان'], slug: 'two');
    pastOrder(segmentGuest($one, '+97455550050'), 3000, now()->subDays(2));
    pastOrder(segmentGuest($two, '+97455550051'), 3000, now()->subDays(2));

    app(Segments::class)->compute($one);
    app(Segments::class)->compute($two);
    app(Segments::class)->compute($one);

    expect(CustomerSegment::withoutTenancy()->where('restaurant_id', $two->id)->count())->toBe(1)
        ->and(CustomerSegment::withoutTenancy()->where('restaurant_id', $one->id)->count())->toBe(1);
});

it('never calls a guest both new and inactive (200 random guests)', function () {
    $restaurant = restaurant();
    mt_srand(7);

    foreach (range(1, 200) as $i) {
        $guest = segmentGuest($restaurant, '+9745570'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
        foreach (range(1, mt_rand(1, 4)) as $n) {
            pastOrder($guest, mt_rand(500, 20000), now()->subDays(mt_rand(0, 365)));
        }
    }

    app(Segments::class)->compute($restaurant);

    $both = CustomerSegment::withoutTenancy()->whereIn('segment', ['new', 'inactive'])
        ->groupBy('customer_id')->havingRaw('count(distinct segment) > 1')->pluck('customer_id');

    expect($both)->toBeEmpty()
        ->and(CustomerSegment::withoutTenancy()->where('segment', 'inactive')->count())->toBeGreaterThan(0)
        ->and(CustomerSegment::withoutTenancy()->where('segment', 'new')->count())->toBeGreaterThan(0);
});

it('runs every night from the command', function () {
    $restaurant = restaurant();
    pastOrder(segmentGuest($restaurant, '+97455550060'), 3000, now()->subDays(2));

    $this->artisan('crm:segments')->assertSuccessful();

    expect(CustomerSegment::withoutTenancy()->where('segment', 'new')->count())->toBe(1);
});

it('makes the top 5% of spenders VIP once there are enough guests', function () {
    $restaurant = restaurant();
    $guests = collect(range(1, 40))->map(function (int $i) use ($restaurant): Customer {
        $guest = segmentGuest($restaurant, '+9745580'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
        pastOrder($guest, $i * 1000, now()->subDays(20));

        return $guest;
    });

    app(Segments::class)->compute($restaurant);

    // 5% of 40 = the 2 biggest spenders.
    expect(CustomerSegment::withoutTenancy()->where('segment', 'vip')->pluck('customer_id')->sort()->values()->all())
        ->toBe([$guests[38]->id, $guests[39]->id]);
});
