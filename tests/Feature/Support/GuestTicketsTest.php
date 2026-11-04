<?php

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\Position;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Feedback\Actions\RateOrder;
use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\OpsAlert;
use App\Domain\Ordering\Models\Order;
use App\Domain\Support\Actions\GuestReply;
use App\Domain\Support\Actions\OpenTicket;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketMessage;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\OrderStatus as OrderPage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('en');
});

/** @return array{Restaurant, Customer, Order} a guest with a two-line order picked up just now */
function ticketShop(string $slug = 'masar'): array
{
    $restaurant = restaurant(slug: $slug);
    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+9745555'.random_int(1000, 9999), 'name' => 'Sara']);
    $order = pastOrder($guest, 4200, now(), attributes: ['locale' => 'en']);

    foreach (['Burger', 'Fries'] as $name) {
        $order->items()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => $name, 'ar' => $name], 'quantity' => 1, 'unit_price_minor' => 2100, 'line_total_minor' => 2100]);
    }

    return [$restaurant, $guest, $order->fresh('items')];
}

function signInReporter(Customer $customer): void
{
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'customer_id' => $customer->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$customer->restaurant_id}", $token);
    app()->forgetScopedInstances();
}

function ticketRefusal(callable $call): ?string
{
    try {
        $call();
    } catch (TicketRefused $refused) {
        return $refused->reason;
    }

    return null;
}

it('opens one ticket per order within 7 days, numbered per restaurant', function () {
    [, $guest, $order] = ticketShop();
    [, $otherGuest, $otherOrder] = ticketShop('elsewhere');

    $ticket = app(OpenTicket::class)->handle($order, $guest, TicketCategory::MissingItem, [$order->items[1]->id], 'No fries in the bag');

    expect($ticket->displayNumber())->toBe('T-1001')
        ->and($ticket->status)->toBe(SupportTicket::OPEN)
        ->and($ticket->branch_id)->toBe($order->branch_id)
        ->and($ticket->messages)->toHaveCount(1)
        ->and($ticket->messages->first())->author->toBe('guest')->body->toBe('No fries in the bag')
        ->and(app(OpenTicket::class)->handle($otherOrder, $otherGuest, TicketCategory::Quality, [], 'Cold')->displayNumber())->toBe('T-1001')
        ->and(ticketRefusal(fn () => app(OpenTicket::class)->handle($order, $guest, TicketCategory::Other, [], 'Again')))->toBe('already_open');

    $late = pastOrder($guest, 1000, now()->subDays(8));
    expect(ticketRefusal(fn () => app(OpenTicket::class)->handle($late, $guest, TicketCategory::Other, [], 'Late')))->toBe('too_late');
});

it('keeps only items from the order and needs a description', function () {
    [, $guest, $order] = ticketShop();
    [, , $foreign] = ticketShop('elsewhere');

    expect(ticketRefusal(fn () => app(OpenTicket::class)->handle($order, $guest, TicketCategory::WrongItem, [], '   ')))->toBe('invalid');

    $ticket = app(OpenTicket::class)->handle($order, $guest, TicketCategory::WrongItem, [$order->items[0]->id, $foreign->items[0]->id, 999999], 'Got chicken, not beef');
    expect($ticket->items)->toBe([$order->items[0]->id]);
});

it('refuses another guest and orders not picked up', function () {
    [$restaurant, $guest, $order] = ticketShop();
    $stranger = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455550000']);
    $open = pastOrder($guest, 1000, now(), status: 'preparing');

    expect(ticketRefusal(fn () => app(OpenTicket::class)->handle($order, $stranger, TicketCategory::Other, [], 'Hi')))->toBe('not_yours')
        ->and(ticketRefusal(fn () => app(OpenTicket::class)->handle($open, $guest, TicketCategory::Other, [], 'Hi')))->toBe('not_picked_up')
        ->and(SupportTicket::withoutTenancy()->count())->toBe(0);
});

it('alerts the branch managers once when a ticket opens', function () {
    Notification::fake();
    [$restaurant, $guest, $order] = ticketShop();
    $owner = ownerOf($restaurant);

    app(OpenTicket::class)->handle($order, $guest, TicketCategory::MissingItem, [], 'No fries');

    Notification::assertSentToTimes($owner, OpsAlert::class, 1);
    Notification::assertSentTo($owner, fn (OpsAlert $alert): bool => $alert->kind === AlertKind::TicketOpened);
});

it('lets the guest follow and reply in the conversation on the order page', function () {
    [$restaurant, $guest, $order] = ticketShop();
    signInReporter($guest);

    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $order])
        ->assertSee(__('support.guest.report'))
        ->call('reportProblem', 'missing_item', [$order->items[1]->id], 'No fries')
        ->assertSee('No fries')
        ->assertSee(__('support.guest.status.open'))
        ->call('replyToTicket', 'Still waiting')
        ->assertSee('Still waiting')
        ->assertDontSee(__('support.guest.report'));

    expect(TicketMessage::withoutTenancy()->where('author', 'guest')->count())->toBe(2);
});

it('stops guest replies once the ticket is resolved', function () {
    [, $guest, $order] = ticketShop();
    $ticket = app(OpenTicket::class)->handle($order, $guest, TicketCategory::Other, [], 'Hi');
    $ticket->forceFill(['status' => SupportTicket::RESOLVED, 'resolved_at' => now()])->save();

    expect(ticketRefusal(fn () => app(GuestReply::class)->handle($ticket->fresh(), $guest, 'Hello?')))->toBe('closed');
});

it('opens the report form pre-filled after a low rating with a missing item', function () {
    [$restaurant, $guest, $order] = ticketShop();
    signInReporter($guest);
    app(RateOrder::class)->handle($order, $guest, 2, ['missing_item'], null);

    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $order])
        ->assertSee(__('support.guest.report_after_rating'))
        ->assertSet('reportCategory', 'missing_item');
});

it('offers the report right after a low rating that names a missing item, without a reload', function () {
    [$restaurant, $guest, $order] = ticketShop();
    signInReporter($guest);

    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $order])
        ->assertDontSee(__('support.guest.report_after_rating'))
        ->call('rate', 2, ['missing_item'], null)
        ->assertSee(__('support.guest.report_after_rating'))
        ->assertSet('reportCategory', 'missing_item');
});

it('blocks a double tap: a second open on the same order is refused', function () {
    [, $guest, $order] = ticketShop();

    app(OpenTicket::class)->handle($order, $guest, TicketCategory::Other, [], 'First');
    expect(ticketRefusal(fn () => app(OpenTicket::class)->handle($order->fresh(), $guest, TicketCategory::Other, [], 'Second')))->toBe('already_open')
        ->and(SupportTicket::withoutTenancy()->count())->toBe(1);
});

it('gives managers the Handle support permission, in existing restaurants too', function () {
    [$restaurant] = ticketShop();

    foreach (['general_manager', 'branch_manager'] as $key) {
        expect(Position::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('key', $key)->sole()->permissions->contains(RestaurantPermission::HandleSupport))->toBeTrue();
    }

    expect(Position::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('key', 'cashier')->sole()->permissions->contains(RestaurantPermission::HandleSupport))->toBeFalse()
        ->and(RestaurantPermission::HandleSupport->getLabel())->toBe('Handle support');
});
