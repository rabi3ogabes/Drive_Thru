<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Ordering\Operations\OperationRefused;
use App\Domain\ReadyCall\Enums\PagerStatus;
use App\Domain\ReadyCall\Events\PagerBroadcast;
use App\Domain\ReadyCall\Models\PagerTicket;
use App\Domain\ReadyCall\Pager;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Http\Middleware\AuthenticateDevice;
use App\Livewire\Customer\Pager as GuestPager;
use App\Livewire\Ops\PagerCounter;
use App\Livewire\Ops\ReadyBoard;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('en');
    config(['messaging.log_when_unconfigured' => true]);
});

function counterBranch(?Restaurant $restaurant = null, string $name = 'Lusail'): Branch
{
    $restaurant ??= restaurant(slug: 'karak', services: [Service::ReadyCall]);

    return app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => $name, 'ar' => $name]]);
}

it('numbers tickets per branch per day and gives each its own private link', function () {
    $lusail = counterBranch();
    $pearl = counterBranch($lusail->restaurant, 'Pearl');
    $pager = app(Pager::class);

    $a = $pager->create($lusail);
    $b = $pager->create($lusail, 'Two lattes', '5555 1234');
    $c = $pager->create($pearl);

    expect([$a->number, $b->number, $c->number])->toBe([1, 2, 1])
        ->and($b->phone)->toBe('+97455551234')
        ->and($b->label)->toBe('Two lattes')
        ->and(strlen($a->token))->toBe(32)
        ->and($a->token)->not->toBe($b->token)
        ->and($a->expires_at->diffInHours($a->created_at, true))->toEqual(6.0);

    $this->travelTo(now()->addDay()->setTime(8, 0));
    expect($pager->create($lusail)->number)->toBe(1);
});

it('refuses tickets without the Order-Ready Call service, and bad phone numbers', function () {
    $branch = counterBranch(restaurant(slug: 'masar'));

    expect(fn () => app(Pager::class)->create($branch))->toThrow(OperationRefused::class, __('ops.refused.pager_service_off'));

    withService($branch->restaurant, Service::ReadyCall);
    expect(fn () => app(Pager::class)->create($branch->fresh(), phone: 'not a phone'))->toThrow(OperationRefused::class, __('ops.refused.pager_phone'));
});

it('rings once on ready, messages the guest once, and only calls again after a pause', function () {
    Event::fake([PagerBroadcast::class]);
    $pager = app(Pager::class);
    $ticket = $pager->create(counterBranch(), phone: '55551234');

    $pager->ready($ticket);

    expect($ticket->fresh())->status->toBe(PagerStatus::Ready)->calls->toBe(1)
        ->and(MessageDelivery::withoutTenancy()->where('purpose', Message::PAGER_READY)->count())->toBe(1);
    expect(fn () => $pager->ready($ticket->fresh()))->toThrow(OperationRefused::class);
    expect(fn () => $pager->callAgain($ticket->fresh()))->toThrow(OperationRefused::class, __('ops.refused.pager_too_soon'));

    $this->travel(Pager::RECALL_COOLDOWN + 1)->seconds();
    $pager->callAgain($ticket->fresh());

    expect($ticket->fresh()->calls)->toBe(2)
        ->and(MessageDelivery::withoutTenancy()->count())->toBe(1);
    Event::assertDispatched(PagerBroadcast::class, fn (PagerBroadcast $e): bool => $e->what === 'called' && ! array_key_exists('phone', $e->broadcastWith()));
});

it('closes collected tickets and expires the rest after six hours', function () {
    $pager = app(Pager::class);
    $branch = counterBranch();
    $done = $pager->create($branch);
    $left = $pager->create($branch);

    $pager->collected($done);
    expect(fn () => $pager->collected($done->fresh()))->toThrow(OperationRefused::class);

    $this->travel(7)->hours();
    $this->artisan('pager:expire')->assertSuccessful();

    expect($left->fresh()->status)->toBe(PagerStatus::Expired)
        ->and($done->fresh()->status)->toBe(PagerStatus::Collected);
});

it('shows the guest their own ticket only, and rings through Livewire state', function () {
    $pager = app(Pager::class);
    $branch = counterBranch();
    $ticket = $pager->create($branch, 'Ice cream');
    $other = restaurant(slug: 'other', services: [Service::ReadyCall]);

    $this->get(route('customer.pager', [$branch->restaurant, $ticket->token]))->assertOk()->assertSee((string) $ticket->number)->assertSee('Ice cream');
    $this->get(route('customer.pager', [$other, $ticket->token]))->assertNotFound();
    $this->get('/karak/p/'.str_repeat('A', 32))->assertNotFound();
    expect($ticket->fresh()->opened_at)->not->toBeNull();

    $page = Livewire::test(GuestPager::class, ['restaurant' => $branch->restaurant, 'token' => $ticket->token])
        ->assertSet('status', 'waiting')->assertSee(__('pager.guest.waiting_title'));

    $pager->ready($ticket);
    $page->call('$refresh')->assertSet('status', 'ready')->assertSet('calls', 1)->assertSee(__('pager.guest.ready_title'));

    $pager->collected($ticket->fresh());
    $page->call('$refresh')->assertSet('status', 'collected')->assertSee(__('pager.guest.collected_title'));
});

it('runs the counter: give a ticket, call the guest, mark collected', function () {
    $branch = counterBranch();
    $this->actingAs(employeeAt($branch->restaurant, 'cashier', ['all_branches' => true]));

    $counter = Livewire::test(PagerCounter::class, ['branchId' => $branch->id])
        ->assertSee(__('pager.counter.empty_waiting'))
        ->set('label', 'Two lattes')->set('phone', '55551234')
        ->call('create')
        ->assertDispatched('ops-toast', tone: 'success');

    $ticket = PagerTicket::withoutTenancy()->sole();
    $counter->assertSet('showing', $ticket->id)->assertSee('Two lattes');

    $counter->call('ready', $ticket->id)->assertSet('showing', null);
    expect($ticket->fresh()->status)->toBe(PagerStatus::Ready);

    $counter->call('collected', $ticket->id);
    expect($ticket->fresh()->status)->toBe(PagerStatus::Collected);
});

it('keeps the pager screens to counter staff with the service, at their branches', function () {
    $branch = counterBranch();
    $restaurant = $branch->restaurant;

    $this->actingAs(employeeAt($restaurant, 'cashier', ['all_branches' => true]))->get(route('ops.screen', [$restaurant, $branch->code, 'pager']))->assertOk();
    $this->actingAs(employeeAt($restaurant, 'kitchen', ['all_branches' => true]))->get(route('ops.screen', [$restaurant, $branch->code, 'pager']))->assertForbidden();

    $restaurant->service(Service::ReadyCall)->forceFill(['status' => 'ended'])->save();
    $this->actingAs(employeeAt($restaurant->fresh(), 'cashier', ['all_branches' => true]))->get(route('ops.screen', [$restaurant, $branch->code, 'pager']))->assertForbidden();
});

it('never shows a ticket from another branch to the counter', function () {
    $lusail = counterBranch();
    $pearl = counterBranch($lusail->restaurant, 'Pearl');
    $theirs = app(Pager::class)->create($pearl);
    $this->actingAs(employeeAt($lusail->restaurant, 'cashier', ['all_branches' => true]));

    Livewire::test(PagerCounter::class, ['branchId' => $lusail->id])->call('ready', $theirs->id)->assertNotFound();
});

it('puts numbers only on the ready board', function () {
    $pager = app(Pager::class);
    $branch = counterBranch();
    $ticket = $pager->create($branch, 'Secret label', '55551234');
    $pager->ready($pager->create($branch));
    $this->actingAs(employeeAt($branch->restaurant, 'cashier', ['all_branches' => true]));

    Livewire::test(ReadyBoard::class, ['branchId' => $branch->id])
        ->assertSee((string) $ticket->number)
        ->assertSee(__('pager.board.latest'))
        ->assertDontSee('Secret label')
        ->assertDontSee('1234');
});

it('lets a guest listen to their live ticket and a paired screen to its branch pager', function () {
    $pager = app(Pager::class);
    $branch = counterBranch();
    $ticket = $pager->create($branch);
    $socket = ['socket_id' => '123.456'];

    $this->postJson(route('realtime.auth'), $socket + ['channel_name' => 'private-pager.'.$ticket->token])->assertOk();
    $this->postJson(route('realtime.auth'), $socket + ['channel_name' => 'private-pager.'.str_repeat('B', 32)])->assertForbidden();

    $code = app(CreateDevice::class)->handle($branch, DeviceKind::Pager, 'Counter iPad');
    $token = pairedToken($this, $code->code);
    $this->withCookie(AuthenticateDevice::COOKIE, $token)->withCredentials()
        ->postJson(route('realtime.auth'), $socket + ['channel_name' => 'private-branch.'.$branch->id.'.pager'])->assertOk();
    $this->withCookie(AuthenticateDevice::COOKIE, $token)->get(route('screen.show'))->assertOk()->assertSee(__('pager.counter.new'));

    $this->travel(7)->hours();
    $this->postJson(route('realtime.auth'), $socket + ['channel_name' => 'private-pager.'.$ticket->token])->assertForbidden();
});

it('speaks Arabic on the guest page', function () {
    app()->setLocale('ar');
    $branch = counterBranch();
    $ticket = app(Pager::class)->create($branch);

    Livewire::test(GuestPager::class, ['restaurant' => $branch->restaurant, 'token' => $ticket->token])
        ->assertSee(__('pager.guest.waiting_title', [], 'ar'));
});
