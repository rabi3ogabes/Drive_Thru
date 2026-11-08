<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Staff\Models\Employee;
use App\Domain\Support\Actions\OpenTicket;
use App\Domain\Support\Actions\RefundTicket;
use App\Domain\Support\Actions\ResolveTicket;
use App\Domain\Support\Actions\RewardTicket;
use App\Domain\Support\Actions\StaffReply;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\App\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\App\Resources\SupportTickets\SupportTicketResource;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app()->setLocale('en');
    config(['messaging.log_when_unconfigured' => true]);
});

/** @return array{Restaurant, Branch, Customer} */
function deskShop(string $slug = 'masar'): array
{
    $restaurant = restaurant(slug: $slug);
    $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'lusail']);
    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+9745555'.random_int(1000, 9999), 'name' => 'Sara']);

    return [$restaurant->fresh(), $branch, $guest];
}

/** A picked-up order (paid online by default) with a ticket about it. */
function deskTicket(Customer $guest, Branch $branch, bool $online = true, string $body = 'No fries in the bag'): SupportTicket
{
    $order = pastOrder($guest, 4000, now(), attributes: ['branch_id' => $branch->id, 'locale' => 'en', 'payment_method' => $online ? 'card' : 'pay_at_pickup']);
    Payment::withoutTenancy()->create([
        'restaurant_id' => $guest->restaurant_id, 'order_id' => $order->id, 'method' => $online ? PaymentMethod::Card : PaymentMethod::PayAtPickup,
        'gateway' => $online ? 'fake' : Payment::COUNTER, 'status' => PaymentStatus::Paid, 'amount_minor' => 4000, 'currency' => 'QAR', 'reference' => 'T-'.$order->id,
    ]);

    return app(OpenTicket::class)->handle($order, $guest, TicketCategory::MissingItem, [], $body);
}

function deskWorkspace(Restaurant $restaurant, ?User $user = null): User
{
    $user ??= ownerOf($restaurant);
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    return $user;
}

function deskRefusal(callable $call): ?string
{
    try {
        $call();
    } catch (TicketRefused $refused) {
        return $refused->reason;
    }

    return null;
}

it('lists open tickets first with an over-24-hours marker, scoped to the employee branches', function () {
    [$restaurant, $lusail, $guest] = deskShop();
    $pearl = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'pearl']);
    $old = deskTicket($guest, $lusail, body: 'Old problem');
    $old->forceFill(['created_at' => now()->subHours(30)])->save();
    $resolved = deskTicket($guest, $lusail, body: 'Sorted already');
    $resolved->forceFill(['status' => SupportTicket::RESOLVED, 'resolved_at' => now()])->save();
    deskTicket($guest, $pearl, body: 'Pearl problem');

    deskWorkspace($restaurant);
    Livewire::test(ListSupportTickets::class)
        ->assertCanSeeTableRecords([$old->fresh()])
        ->assertSee(__('support.admin.overdue'))
        ->assertSeeInOrder([$old->displayNumber(), $resolved->displayNumber()]);

    $manager = employeeAt($restaurant, 'branch_manager');
    Employee::withoutTenancy()->where('user_id', $manager->id)->sole()->branches()->attach($lusail->id);
    deskWorkspace($restaurant, $manager->fresh());
    Livewire::test(ListSupportTickets::class)->assertSee($old->displayNumber())->assertDontSee('T-1003');

    expect(SupportTicketResource::getNavigationBadge())->toBe('1');
});

it('sends a reply by WhatsApp with the utility template, then shows it to the guest', function () {
    [$restaurant, $lusail, $guest] = deskShop();
    $ticket = deskTicket($guest, $lusail);
    $user = deskWorkspace($restaurant);

    $message = app(StaffReply::class)->handle($ticket, $user, 'So sorry, Sara. We will refund the fries.');

    $delivery = MessageDelivery::withoutTenancy()->sole();
    expect($delivery->purpose)->toBe(Message::SUPPORT)
        ->and($message->message_delivery_id)->toBe($delivery->id)
        ->and($ticket->fresh())->status->toBe(SupportTicket::ANSWERED)->first_reply_at->not->toBeNull()
        ->and(Activity::query()->where('event', 'support.replied')->count())->toBe(1);
});

it('builds the WhatsApp message with four template parameters', function () {
    [$restaurant, $lusail, $guest] = deskShop();
    $ticket = deskTicket($guest, $lusail);

    $message = StaffReply::message($ticket->load('order', 'customer'), str_repeat('a', 1200));

    expect($message->purpose)->toBe(Message::SUPPORT)
        ->and($message->parameters)->toHaveCount(4)
        ->and($message->parameters[1])->toBe($ticket->displayNumber())
        ->and(mb_strlen($message->parameters[2]))->toBeLessThanOrEqual(900)
        ->and($message->parameters[3])->toContain('/masar/orders/');
});

it('refunds an online payment in part, then refuses more than what is left', function () {
    [$restaurant, $lusail, $guest] = deskShop();
    $ticket = deskTicket($guest, $lusail);
    $user = deskWorkspace($restaurant);

    $refund = app(RefundTicket::class)->handle($ticket, $user, 1500);
    expect($refund->amount_minor)->toBe(1500)
        ->and($ticket->fresh())->outcome->toBe('refund')->refund_id->toBe($refund->id)
        ->and(deskRefusal(fn () => app(RefundTicket::class)->handle($ticket->fresh(), $user, 3000)))->toBe('too_much')
        ->and(app(RefundTicket::class)->handle($ticket->fresh(), $user, null)->amount_minor)->toBe(2500)
        ->and(deskRefusal(fn () => app(RefundTicket::class)->handle($ticket->fresh(), $user, null)))->toBe('nothing_to_refund');
});

it('refuses a refund for a cash order and offers a reward instead', function () {
    [$restaurant, $lusail, $guest] = deskShop();
    $ticket = deskTicket($guest, $lusail, online: false);
    $user = deskWorkspace($restaurant);
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Free karak', 'ar' => 'كرك مجاني'], 'type' => RewardType::FixedOff, 'value' => 500]);

    expect(deskRefusal(fn () => app(RefundTicket::class)->handle($ticket, $user, null)))->toBe('not_online');

    $wallet = app(RewardTicket::class)->handle($ticket, $user, $reward);
    expect($wallet->source)->toBe('support')
        ->and($wallet->customer_id)->toBe($guest->id)
        ->and($ticket->fresh())->outcome->toBe('reward')->wallet_reward_id->toBe($wallet->id);
});

it('needs Handle support to see tickets and Refund payments to refund', function () {
    [$restaurant, $lusail, $guest] = deskShop();
    $ticket = deskTicket($guest, $lusail);

    deskWorkspace($restaurant, employeeAt($restaurant, 'cashier', ['all_branches' => true]));
    $this->get(ListSupportTickets::getUrl(tenant: $restaurant))->assertForbidden();

    $marketing = deskWorkspace($restaurant, employeeAt($restaurant, 'marketing', ['all_branches' => true]));
    expect(deskRefusal(fn () => app(RefundTicket::class)->handle($ticket, $marketing, null)))->toBe('forbidden');

    $manager = employeeAt($restaurant, 'branch_manager', ['all_branches' => true]);
    deskWorkspace($restaurant, $manager);
    $this->get(ViewSupportTicket::getUrl(['record' => $ticket], tenant: $restaurant))->assertOk()->assertSee('No fries in the bag');
    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
        ->callAction('refund', data: ['amount' => '10.00'])
        ->assertHasNoActionErrors();

    expect($ticket->fresh()->refund->amount_minor)->toBe(1000);
});

it('resolves with a closing note and tells the guest the outcome', function () {
    [$restaurant, $lusail, $guest] = deskShop();
    $ticket = deskTicket($guest, $lusail);
    $user = deskWorkspace($restaurant);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
        ->set('reply', 'Here is 10 QAR back.')->call('sendReply')
        ->callAction('resolve', data: ['note' => 'Thank you for telling us.'])
        ->assertHasNoActionErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicket::RESOLVED)
        ->and($ticket->resolved_by)->toBe($user->id)
        ->and($ticket->messages->pluck('body')->all())->toBe(['No fries in the bag', 'Here is 10 QAR back.', 'Thank you for telling us.'])
        ->and(MessageDelivery::withoutTenancy()->where('purpose', Message::SUPPORT)->count())->toBe(2)
        ->and(deskRefusal(fn () => app(ResolveTicket::class)->handle($ticket, $user, null)))->toBe('closed');
});

it('hides other restaurants tickets', function () {
    [$other, $theirBranch, $theirGuest] = deskShop('elsewhere');
    $theirs = deskTicket($theirGuest, $theirBranch, body: 'Not your business');
    [$restaurant] = deskShop();

    deskWorkspace($restaurant);
    Livewire::test(ListSupportTickets::class)->assertDontSee('Not your business');
    $this->get(ViewSupportTicket::getUrl(['record' => $theirs], tenant: $restaurant))->assertNotFound();
});
