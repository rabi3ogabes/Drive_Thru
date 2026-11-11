<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Filament\App\Resources\Branches\BranchResource;

it('tells the owner to add a branch first, with a link, instead of a 403', function (string $locale) {
    app()->setLocale($locale);
    $restaurant = restaurant(slug: 'tea-time');
    $owner = ownerOf($restaurant);

    foreach (['tv', 'kitchen', 'live', 'pickup'] as $screen) {
        $this->actingAs($owner)->withSession(['locale' => $locale])->get(route('ops.go', [$restaurant, $screen]))
            ->assertStatus(409)
            ->assertSee(__('ops.no_branch.title', [], $locale))
            ->assertSee(__('ops.no_branch.owner', [], $locale))
            ->assertSee(__('ops.no_branch.add', [], $locale))
            ->assertSeeHtml('href="'.e(BranchResource::getUrl(panel: 'app', tenant: $restaurant)).'"')
            ->assertDontSee(__('errors.403.title', [], $locale));
    }
})->with(['en', 'ar']);

it('tells staff without a branch to ask their manager, without the add link', function () {
    $restaurant = restaurant(slug: 'tea-time');
    $cashier = employeeAt($restaurant, 'cashier');

    $this->actingAs($cashier)->get(route('ops.go', [$restaurant, 'live']))
        ->assertStatus(409)
        ->assertSee(__('ops.no_branch.unassigned_title'))
        ->assertSee(__('ops.no_branch.staff'))
        ->assertDontSee(__('ops.no_branch.add'));
    $this->actingAs($cashier)->get(route('ops.home', $restaurant))->assertStatus(409)->assertSee(__('ops.no_branch.staff'));

    // Once there is a branch but the person isn't on it, the page says so.
    app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    $this->actingAs($cashier)->get(route('ops.go', [$restaurant, 'live']))->assertStatus(409)->assertSee(__('ops.no_branch.unassigned_title'));
});

it('still refuses people who may not open the screen, and outsiders', function () {
    $restaurant = restaurant(slug: 'tea-time');
    $kitchen = employeeAt($restaurant, 'kitchen', ['all_branches' => true]);
    $stranger = employeeAt(restaurant(['en' => 'Other', 'ar' => 'آخر'], slug: 'other'), 'general_manager', ['all_branches' => true]);

    $this->actingAs($kitchen)->get(route('ops.go', [$restaurant, 'pickup']))->assertForbidden();
    $this->actingAs($stranger)->get(route('ops.go', [$restaurant, 'tv']))->assertForbidden()->assertDontSee(__('ops.no_branch.title'));
    $this->actingAs($stranger)->get(route('ops.home', $restaurant))->assertForbidden();
});

it('opens the screen at the first branch once one exists', function () {
    $restaurant = restaurant(slug: 'tea-time');
    $owner = ownerOf($restaurant);
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);

    $this->actingAs($owner)->get(route('ops.go', [$restaurant, 'tv']))->assertRedirect(route('ops.screen', [$restaurant, $branch->code, 'tv']));
});
