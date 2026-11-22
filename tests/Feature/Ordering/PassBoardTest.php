<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Ordering\Enums\OpsBoardStyle;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Ops\ExecutiveBoard;
use App\Livewire\Ops\PassBoard;
use Livewire\Livewire;

require_once __DIR__.'/../../Support/PassHelpers.php';

function passRestaurant(): Restaurant
{
    $restaurant = restaurant(slug: 'tea-time');
    $restaurant->forceFill(['ops_board' => OpsBoardStyle::Pass])->save();

    return $restaurant;
}

it('shows the pass board when the restaurant uses it, the classic board otherwise', function () {
    $restaurant = passRestaurant();
    passBranch($restaurant);
    $owner = ownerOf($restaurant);

    $this->actingAs($owner)->get(route('ops.overview', $restaurant))->assertOk()->assertSeeLivewire(PassBoard::class)->assertDontSeeLivewire(ExecutiveBoard::class);

    $restaurant->forceFill(['ops_board' => OpsBoardStyle::Classic])->save();
    $this->actingAs($owner)->get(route('ops.overview', $restaurant))->assertOk()->assertSeeLivewire(ExecutiveBoard::class)->assertDontSeeLivewire(PassBoard::class);
});

it('keeps the same access rules as the classic overview', function () {
    $restaurant = passRestaurant();
    passBranch($restaurant);
    $kitchen = employeeAt($restaurant, 'kitchen', ['all_branches' => true]);
    $stranger = employeeAt(restaurant(['en' => 'Other', 'ar' => 'آخر'], slug: 'other'), 'general_manager', ['all_branches' => true]);

    $this->actingAs($kitchen)->get(route('ops.overview', $restaurant))->assertForbidden();
    $this->actingAs($stranger)->get(route('ops.overview', $restaurant))->assertForbidden();
    $this->actingAs(employeeAt($restaurant, 'general_manager', ['all_branches' => true]))->get(route('ops.overview', $restaurant))->assertOk();
});

it('switches between branches and never shows a branch of another restaurant', function () {
    $restaurant = passRestaurant();
    $first = passBranch($restaurant);
    $second = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Pearl', 'ar' => 'اللؤلؤة']]);
    $foreign = passBranch(restaurant(['en' => 'Other', 'ar' => 'آخر'], slug: 'other'));
    passOrder($first, items: [['First karak', 'drinks']]);
    passOrder($second, items: [['Second karak', null]]);
    passOrder($foreign, items: [['Foreign karak', 'drinks']]);
    $this->actingAs(ownerOf($restaurant));

    Livewire::test(PassBoard::class, ['restaurantId' => $restaurant->id])
        ->assertSet('branchId', $first->id)->assertSee('First karak')->assertDontSee('Second karak')
        ->call('selectBranch', $second->id)->assertSee('Second karak')->assertDontSee('First karak')
        ->call('selectBranch', $foreign->id)->assertSet('branchId', $first->id)->assertDontSee('Foreign karak');

    Livewire::withQueryParams(['branch' => $foreign->id])->test(PassBoard::class, ['restaurantId' => $restaurant->id])
        ->assertSet('branchId', $first->id)->assertDontSee('Foreign karak');
});

it('shows the add-a-branch page when the restaurant has no branch', function () {
    $restaurant = passRestaurant();

    $this->actingAs(ownerOf($restaurant))->get(route('ops.overview', $restaurant))
        ->assertStatus(409)->assertSee(__('ops.no_branch.title'));
});

it('starts an order through the order desk', function () {
    $restaurant = passRestaurant();
    $branch = passBranch($restaurant);
    $order = passOrder($branch);
    $this->actingAs(ownerOf($restaurant));

    Livewire::test(PassBoard::class, ['restaurantId' => $restaurant->id])
        ->call('start', $order->id)
        ->assertDispatched('ops-toast', tone: 'success');

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing)
        ->and($order->history()->where('to', 'preparing')->exists())->toBeTrue();
});

it('hides Start without the kitchen permission and refuses the call', function () {
    $restaurant = passRestaurant();
    $branch = passBranch($restaurant);
    $order = passOrder($branch);
    $accountant = employeeAt($restaurant, 'accountant', ['all_branches' => true]);
    $this->actingAs($accountant);

    Livewire::test(PassBoard::class, ['restaurantId' => $restaurant->id])
        ->assertSet('canStart', false)
        ->assertDontSeeHtml('wire:click="start(')
        ->call('start', $order->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Received);
});

it('refuses a second start of the same order with a message', function () {
    $restaurant = passRestaurant();
    $branch = passBranch($restaurant);
    $order = passOrder($branch, ['status' => 'preparing']);
    $this->actingAs(ownerOf($restaurant));

    Livewire::test(PassBoard::class, ['restaurantId' => $restaurant->id])
        ->call('start', $order->id)
        ->assertDispatched('ops-toast', tone: 'error');

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});

it('renders in Arabic and English', function (string $locale) {
    $restaurant = passRestaurant();
    passOrder(passBranch($restaurant));

    $this->actingAs(ownerOf($restaurant))->withSession(['locale' => $locale])->get(route('ops.overview', $restaurant))
        ->assertOk()
        ->assertSee(__('ops.pass.on_pass', [], $locale))
        ->assertSee(__('ops.pass.next', [], $locale))
        ->assertSee(__('ops.pass.stations', [], $locale));
})->with(['en', 'ar']);
