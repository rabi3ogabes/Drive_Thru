<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Loyalty\Actions\StampRequests;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Loyalty\Models\StampRequest;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Menu\Models\Product;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Services\Enums\Service;
use App\Livewire\Customer\Rewards;
use App\Livewire\Ops\StampCounter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    app()->setLocale('en');
    config(['messaging.log_when_unconfigured' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Qatar'));
});

/** @return array{0: Customer, 1: StampProgram, 2: Branch} */
function counterShop(int $needed = 9, int $cap = 3, string $slug = 'centra'): array
{
    $restaurant = restaurant(slug: $slug, services: [Service::Rewards]);
    $coffee = Product::factory()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Coffee', 'ar' => 'قهوة']]);
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Free medium coffee', 'ar' => 'قهوة وسط مجانية'], 'type' => RewardType::FreeItem, 'product_id' => $coffee->id]);
    $program = StampProgram::withoutTenancy()->create([
        'restaurant_id' => $restaurant->id, 'name' => ['en' => 'Coffee card', 'ar' => 'بطاقة القهوة'],
        'target_type' => 'product', 'target_id' => $coffee->id, 'stamps_needed' => $needed, 'reward_id' => $reward->id, 'daily_cap' => $cap,
    ]);
    $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'west-bay']);
    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455551234', 'name' => 'Noura']);

    return [$guest, $program, $branch];
}

function signInAt(Customer $guest): void
{
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $guest->restaurant_id, 'customer_id' => $guest->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$guest->restaurant_id}", $token);
    app()->forgetScopedInstances();
}

function cardOf(Customer $guest, StampProgram $program): int
{
    return StampCard::withoutTenancy()->where('customer_id', $guest->id)->where('stamp_program_id', $program->id)->value('stamps') ?? 0;
}

it('runs the counter QR flow: the guest asks, the waiter approves, the card fills live', function () {
    [$guest, $program, $branch] = counterShop();
    signInAt($guest);

    $page = Livewire::withQueryParams(['at' => 'west-bay'])->test(Rewards::class, ['restaurant' => $guest->restaurant])
        ->assertSee(__('guest.rewards.counter.ask'))
        ->call('askStamp')
        ->assertSee(__('guest.rewards.counter.waiting'));

    $request = StampRequest::withoutTenancy()->sole();
    expect($request->branch_id)->toBe($branch->id)->and($request->isWaiting())->toBeTrue();

    $cashier = employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]);
    $this->actingAs($cashier);
    Livewire::test(StampCounter::class, ['branchId' => $branch->id])
        ->assertSee('Noura')
        ->assertSee(trans_choice('ops.stamps.waiting', 1, ['count' => 1]))
        ->call('approve', $request->id)
        ->assertDispatched('ops-toast', tone: 'success')
        ->assertDontSee(trans_choice('ops.stamps.waiting', 1, ['count' => 1]));

    expect(cardOf($guest, $program))->toBe(1)
        ->and($request->fresh()->status)->toBe(StampRequest::APPROVED)
        ->and($request->fresh()->decided_by)->toBe($cashier->id)
        ->and(MessageDelivery::withoutTenancy()->count())->toBe(0);

    $page->call('checkStamp')
        ->assertDispatched('stamp-added')
        ->assertSet('stampRequestId', null)
        ->assertSee(__('guest.rewards.counter.ask'));
});

it('lets the waiter choose the card and quantity when there are several cards, and fills the card', function () {
    [$guest, $program, $branch] = counterShop(needed: 3, cap: 5);
    StampProgram::withoutTenancy()->create([
        'restaurant_id' => $guest->restaurant_id, 'name' => ['en' => 'Sandwich card', 'ar' => 'بطاقة الساندويتش'],
        'target_type' => 'product', 'target_id' => $program->target_id, 'stamps_needed' => 6, 'reward_id' => $program->reward_id,
    ]);
    $request = app(StampRequests::class)->ask($guest, $branch);
    $this->actingAs(employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]));

    Livewire::test(StampCounter::class, ['branchId' => $branch->id])
        ->assertSee(__('ops.stamps.choose'))
        ->call('approve', $request->id)
        ->assertSet('customerId', $guest->id)
        ->assertSet('requestId', $request->id)
        ->assertSee(__('ops.stamps.asked', ['name' => 'Noura']))
        ->call('step', $program->id, 1)->call('step', $program->id, 1)->call('step', $program->id, 1)
        ->call('add', $program->id)
        ->assertSet('requestId', null);

    expect($request->fresh()->status)->toBe(StampRequest::APPROVED)
        ->and($request->fresh()->added)->toBe(3)
        ->and(cardOf($guest, $program))->toBe(0)
        ->and(WalletReward::withoutTenancy()->where('customer_id', $guest->id)->sole()->source)->toBe('stamps');
});

it('keeps the request open when today’s cap is reached, and lets the waiter decline it', function () {
    [$guest, $program, $branch] = counterShop(cap: 1);
    $waiter = employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]);
    app(StampRequests::class)->approve(app(StampRequests::class)->ask($guest, $branch), $program, 1, $waiter);
    $second = app(StampRequests::class)->ask($guest, $branch);
    $this->actingAs($waiter);

    Livewire::test(StampCounter::class, ['branchId' => $branch->id])
        ->call('approve', $second->id)
        ->assertDispatched('ops-toast', tone: 'error')
        ->call('decline', $second->id);

    expect(cardOf($guest, $program))->toBe(1)
        ->and($second->fresh()->status)->toBe(StampRequest::DECLINED)
        ->and(Activity::query()->where('event', 'loyalty.stamp_request_declined')->sole()->causer_id)->toBe($waiter->id);

    signInAt($guest);
    Livewire::test(Rewards::class, ['restaurant' => $guest->restaurant])
        ->assertSet('stampRequestId', null)
        ->assertDontSee(__('guest.rewards.counter.waiting'));
});

it('tells the guest when nobody confirmed in time', function () {
    [$guest, , $branch] = counterShop();
    signInAt($guest);
    $page = Livewire::withQueryParams(['at' => 'west-bay'])->test(Rewards::class, ['restaurant' => $guest->restaurant])->call('askStamp');

    $this->travel(StampRequest::MINUTES + 1)->minutes();

    $page->call('checkStamp')->assertDispatched('toast', tone: 'error')->assertSet('stampRequestId', null);
    expect(StampRequest::withoutTenancy()->sole()->status)->toBe(StampRequest::EXPIRED);

    $this->actingAs(employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]));
    Livewire::test(StampCounter::class, ['branchId' => $branch->id])->assertDontSee('Noura');
});

it('needs the counter QR and a signed-in guest before asking', function () {
    [$guest] = counterShop();

    Livewire::withQueryParams(['at' => 'west-bay'])->test(Rewards::class, ['restaurant' => $guest->restaurant])
        ->assertSee(__('guest.rewards.counter.sign_in'))
        ->call('askStamp')
        ->assertDispatched('toast', tone: 'error');

    session()->forget('stamp_counter.'.$guest->restaurant_id);
    signInAt($guest);
    Livewire::withQueryParams([])->test(Rewards::class, ['restaurant' => $guest->restaurant])
        ->assertSee(__('guest.rewards.counter.scan_hint'))
        ->assertDontSee(__('guest.rewards.counter.ask'));

    Livewire::withQueryParams(['at' => 'no-such-branch'])->test(Rewards::class, ['restaurant' => $guest->restaurant])
        ->assertDontSee(__('guest.rewards.counter.ask'));

    expect(StampRequest::withoutTenancy()->count())->toBe(0);
});

it('keeps one open request per guest and limits how often they ask', function () {
    [$guest, , $branch] = counterShop();
    $requests = app(StampRequests::class);

    $first = $requests->ask($guest, $branch);
    $second = $requests->ask($guest, $branch);

    expect($first->fresh()->status)->toBe(StampRequest::CANCELLED)
        ->and($second->fresh()->isWaiting())->toBeTrue();

    $requests->ask($guest, $branch);
    $requests->ask($guest, $branch);

    expect(fn () => $requests->ask($guest, $branch))->toThrow(RewardUnavailable::class);
});

it('never shows or approves another branch’s or restaurant’s requests', function () {
    [$guest, , $branch] = counterShop();
    $otherBranch = Branch::factory()->create(['restaurant_id' => $guest->restaurant_id, 'code' => 'lusail']);
    [$stranger, , $strangerBranch] = counterShop(slug: 'elsewhere');
    $mine = app(StampRequests::class)->ask($guest, $branch);
    $theirs = app(StampRequests::class)->ask($stranger, $strangerBranch);

    $this->actingAs(employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]));

    Livewire::test(StampCounter::class, ['branchId' => $otherBranch->id])
        ->assertDontSee('Noura')
        ->call('approve', $mine->id)
        ->assertDispatched('ops-toast', tone: 'error');

    Livewire::test(StampCounter::class, ['branchId' => $branch->id])
        ->call('decline', $theirs->id);

    expect($mine->fresh()->isWaiting())->toBeTrue()
        ->and($theirs->fresh()->isWaiting())->toBeTrue();
    expect(fn () => app(StampRequests::class)->ask($guest, $strangerBranch))->toThrow(NotFoundHttpException::class);
});

it('keeps the stamp screen from kitchen staff', function () {
    [$guest, , $branch] = counterShop();
    $this->actingAs(employeeAt($guest->restaurant, 'kitchen', ['all_branches' => true]));

    Livewire::test(StampCounter::class, ['branchId' => $branch->id])->assertForbidden();
});

it('offers a stamp counter QR poster per branch in Links & QR', function () {
    [$guest, , $branch] = counterShop();
    $owner = ownerOf($guest->restaurant);

    $this->actingAs($owner)->get('/app/centra/links')->assertOk()
        ->assertSee(__('links.stamps_name', ['branch' => $branch->name]))
        ->assertSee('centra/rewards?at=west-bay', false);

    $this->get(route('qr.download', ['restaurant' => 'centra', 'format' => 'png', 'branch' => 'west-bay', 'kind' => 'stamps']))
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="centra-west-bay-stamps-qr.png"');
    $this->get(route('qr.download', ['restaurant' => 'centra', 'format' => 'png', 'kind' => 'stamps']))->assertNotFound();
});
