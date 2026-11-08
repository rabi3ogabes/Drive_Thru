<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Feedback\RatingStats;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\Customers\Pages\ViewGuest;
use App\Filament\App\Resources\Ratings\Pages\ListRatings;
use App\Filament\App\Widgets\RestaurantOverview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('en');
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Qatar'));
});

/** @return array{Restaurant, Branch, Branch, Customer} */
function ratingsShop(string $slug = 'masar'): array
{
    $restaurant = restaurant(slug: $slug);
    $lusail = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'lusail']);
    $pearl = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'pearl']);
    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+9745555'.random_int(1000, 9999), 'name' => 'Sara']);

    return [$restaurant->fresh(), $lusail, $pearl, $guest];
}

function ratingOf(Customer $guest, Branch $branch, int $stars, array $tags = [], ?string $comment = null, ?CarbonInterface $at = null): OrderRating
{
    $at ??= now();
    $order = pastOrder($guest, 3000, $at, attributes: ['branch_id' => $branch->id]);

    return OrderRating::withoutTenancy()->create(['restaurant_id' => $guest->restaurant_id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'customer_id' => $guest->id, 'stars' => $stars, 'tags' => $tags, 'comment' => $comment, 'created_at' => $at]);
}

function ratingsWorkspace(Restaurant $restaurant, ?User $user = null): void
{
    test()->actingAs($user ?? ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);
}

it('summarises ratings: average, distribution, top tags and 30 days', function () {
    [$restaurant, $lusail, $pearl, $guest] = ratingsShop();
    ratingOf($guest, $lusail, 5, ['fast', 'friendly']);
    ratingOf($guest, $lusail, 4, ['fast']);
    ratingOf($guest, $pearl, 2, ['cold'], 'Cold fries', now()->subDays(3));
    ratingOf($guest, $pearl, 1, [], null, now()->subDays(40)); // outside the period

    $stats = RatingStats::for($restaurant->id, null, now()->subDays(29)->startOfDay(), now());

    expect($stats['count'])->toBe(3)
        ->and($stats['average'])->toBe(3.7)
        ->and($stats['distribution'])->toBe([1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 1])
        ->and($stats['tags'])->toBe(['fast' => 2, 'friendly' => 1, 'cold' => 1])
        ->and($stats['days'])->toHaveCount(30)
        ->and(collect($stats['days'])->last())->toBe(['date' => '2026-11-04', 'average' => 4.5, 'count' => 2])
        ->and(RatingStats::for($restaurant->id, [$pearl->id], now()->subDays(29)->startOfDay(), now())['count'])->toBe(1);
});

it('lists ratings with branch, stars and tag filters, scoped to the employee branches', function () {
    [$restaurant, $lusail, $pearl, $guest] = ratingsShop();
    ratingOf($guest, $lusail, 5, ['fast'], 'Lovely karak');
    ratingOf($guest, $pearl, 2, ['cold'], 'Cold fries');

    ratingsWorkspace($restaurant);
    Livewire::test(ListRatings::class)
        ->assertSee('Lovely karak')->assertSee('Cold fries')
        ->filterTable('stars', 2)->assertDontSee('Lovely karak')->assertSee('Cold fries')
        ->resetTableFilters()
        ->filterTable('tag', 'fast')->assertSee('Lovely karak')->assertDontSee('Cold fries');

    $manager = employeeAt($restaurant, 'branch_manager');
    Employee::withoutTenancy()->where('user_id', $manager->id)->sole()->branches()->attach($lusail->id);
    ratingsWorkspace($restaurant, $manager->fresh());
    Livewire::test(ListRatings::class)->assertSee('Lovely karak')->assertDontSee('Cold fries');
});

it('shows the 30-day rating on the dashboard and the guest profile', function () {
    [$restaurant, $lusail, , $guest] = ratingsShop();
    ratingOf($guest, $lusail, 5, [], 'Best burger in Lusail');
    ratingOf($guest, $lusail, 4);

    ratingsWorkspace($restaurant);
    Livewire::test(RestaurantOverview::class)->assertSee(__('feedback.admin.stat'))->assertSee('4.5');
    Livewire::test(ViewGuest::class, ['record' => $guest->id])->assertSee(__('feedback.admin.profile'))->assertSee('Best burger in Lusail');
});

it('forbids staff without View reports, and hides other restaurants', function () {
    [$other, $theirBranch, , $theirGuest] = ratingsShop('elsewhere');
    ratingOf($theirGuest, $theirBranch, 1, [], 'Not ours');
    [$restaurant] = ratingsShop();

    ratingsWorkspace($restaurant, employeeAt($restaurant, 'cashier', ['all_branches' => true]));
    $this->get(ListRatings::getUrl(tenant: $restaurant))->assertForbidden();

    ratingsWorkspace($restaurant);
    Livewire::test(ListRatings::class)->assertDontSee('Not ours');
});
