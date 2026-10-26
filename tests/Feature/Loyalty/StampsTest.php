<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampEvent;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\Stamps;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\CartPricer;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\StampPrograms\Pages\ManageStampPrograms;
use App\Livewire\Customer\Rewards;
use App\Livewire\Ops\StampCounter;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    app()->setLocale('en');
    config(['messaging.log_when_unconfigured' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Qatar'));
});

/** @return array{0: Customer, 1: StampProgram, 2: Product} */
function stampShop(int $needed = 5, int $cap = 3, string $slug = 'karak'): array
{
    $restaurant = restaurant(slug: $slug, services: [Service::DriveThru, Service::Rewards]);
    $iceCream = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 1500, 'name' => ['en' => 'Ice cream', 'ar' => 'آيس كريم']]);
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Free ice cream', 'ar' => 'آيس كريم مجاني'], 'type' => RewardType::FreeItem, 'product_id' => $iceCream->id]);
    $program = StampProgram::withoutTenancy()->create([
        'restaurant_id' => $restaurant->id, 'name' => ['en' => 'Ice cream card', 'ar' => 'بطاقة الآيس كريم'],
        'target_type' => 'product', 'target_id' => $iceCream->id, 'stamps_needed' => $needed, 'reward_id' => $reward->id, 'daily_cap' => $cap,
    ]);
    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455551234', 'name' => 'Sara']);

    return [$guest, $program, $iceCream];
}

function stampsOn(Customer $guest, StampProgram $program): int
{
    return StampCard::withoutTenancy()->where('customer_id', $guest->id)->where('stamp_program_id', $program->id)->value('stamps') ?? 0;
}

it('adds a stamp per counted item when the order is picked up, once', function () {
    [$guest, $program, $iceCream] = stampShop();
    $other = Product::factory()->create(['restaurant_id' => $guest->restaurant_id]);
    $order = pastOrder($guest, 3000, now(), 'ready', [$iceCream->id, $iceCream->id, $other->id]);

    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::PickedUp, 'staff');

    expect(stampsOn($guest, $program))->toBe(2);

    app(Stamps::class)->earnForOrder($order->fresh());
    expect(stampsOn($guest, $program))->toBe(2);
});

it('counts a whole category when the card is for a category', function () {
    [$guest, $program, $iceCream] = stampShop();
    $desserts = MenuCategory::withoutTenancy()->create(['restaurant_id' => $guest->restaurant_id, 'name' => ['en' => 'Desserts', 'ar' => 'حلويات']]);
    $cake = Product::factory()->create(['restaurant_id' => $guest->restaurant_id, 'menu_category_id' => $desserts->id]);
    $iceCream->forceFill(['menu_category_id' => $desserts->id])->save();
    $program->update(['target_type' => 'category', 'target_id' => $desserts->id]);

    app(Stamps::class)->earnForOrder(pastOrder($guest, 3000, now(), 'picked_up', [$iceCream->id, $cake->id]));

    expect(stampsOn($guest, $program))->toBe(2);
});

it('caps stamps per guest per day, then allows more the next day', function () {
    [$guest, $program, $iceCream] = stampShop(needed: 10, cap: 3);
    $stamps = app(Stamps::class);

    $stamps->earnForOrder(pastOrder($guest, 7500, now(), 'picked_up', array_fill(0, 5, $iceCream->id)));
    expect(stampsOn($guest, $program))->toBe(3);

    $stamps->earnForOrder(pastOrder($guest, 1500, now(), 'picked_up', [$iceCream->id]));
    expect(stampsOn($guest, $program))->toBe(3);

    $this->travelTo(now()->addDay());
    $stamps->earnForOrder(pastOrder($guest, 3000, now(), 'picked_up', [$iceCream->id, $iceCream->id]));
    expect(stampsOn($guest, $program))->toBe(5);
});

it('turns a full card into a wallet reward and carries extra stamps over', function () {
    [$guest, $program, $iceCream] = stampShop(needed: 5, cap: 3);
    $stamps = app(Stamps::class);

    $stamps->earnForOrder(pastOrder($guest, 4500, now(), 'picked_up', array_fill(0, 3, $iceCream->id)));
    $this->travelTo(now()->addDay());
    $stamps->earnForOrder(pastOrder($guest, 4500, now(), 'picked_up', array_fill(0, 3, $iceCream->id)));

    $wallet = WalletReward::withoutTenancy()->sole();
    expect(stampsOn($guest, $program))->toBe(1)
        ->and($wallet->source)->toBe('stamps')
        ->and($wallet->status)->toBe(WalletReward::AVAILABLE)
        ->and(StampCard::withoutTenancy()->sole()->completed_count)->toBe(1);
});

it('takes stamps back on cancel, and withdraws an unused reward the order completed', function () {
    [$guest, $program, $iceCream] = stampShop(needed: 3, cap: 5);
    $stamps = app(Stamps::class);
    $first = pastOrder($guest, 1500, now(), 'picked_up', [$iceCream->id]);
    $stamps->earnForOrder($first);
    $second = pastOrder($guest, 3000, now(), 'picked_up', [$iceCream->id, $iceCream->id]);
    $stamps->earnForOrder($second);
    expect(WalletReward::withoutTenancy()->sole()->status)->toBe(WalletReward::AVAILABLE);

    $stamps->reverseOrder($second, 'cancelled');

    expect(WalletReward::withoutTenancy()->sole()->status)->toBe(WalletReward::CANCELLED)
        ->and(stampsOn($guest, $program))->toBe(1)
        ->and(StampCard::withoutTenancy()->sole()->completed_count)->toBe(0);

    $stamps->reverseOrder($second, 'cancelled');
    expect(StampEvent::withoutTenancy()->where('source', 'reversal')->count())->toBe(1);
});

it('keeps a used reward and records that it was already used', function () {
    [$guest, $program, $iceCream] = stampShop(needed: 2, cap: 5);
    $stamps = app(Stamps::class);
    $order = pastOrder($guest, 3000, now(), 'picked_up', [$iceCream->id, $iceCream->id]);
    $stamps->earnForOrder($order);
    WalletReward::withoutTenancy()->sole()->forceFill(['status' => WalletReward::USED])->save();

    $stamps->reverseOrder($order, 'refunded');

    expect(WalletReward::withoutTenancy()->sole()->status)->toBe(WalletReward::USED)
        ->and(stampsOn($guest, $program))->toBe(0)
        ->and(StampEvent::withoutTenancy()->where('source', 'reversal')->value('note'))->toContain('already used');
});

it('adds stamps at the counter under the staff member’s name and tells the guest', function () {
    [$guest, $program] = stampShop();
    $cashier = employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]);

    $result = app(Stamps::class)->addAtCounter($guest, $program, 2, $cashier, 'Paid at till');

    expect($result['added'])->toBe(2)
        ->and(stampsOn($guest, $program))->toBe(2)
        ->and(Activity::query()->where('event', 'loyalty.stamps_added')->sole()->causer_id)->toBe($cashier->id)
        ->and(MessageDelivery::withoutTenancy()->where('purpose', Message::STAMPS)->count())->toBe(1);
});

it('refuses a stamp card from another restaurant at the counter', function () {
    [$guest] = stampShop();
    [, $theirs] = stampShop(slug: 'other');

    expect(fn () => app(Stamps::class)->addAtCounter($guest, $theirs, 1, ownerOf($guest->restaurant)))
        ->toThrow(NotFoundHttpException::class);
});

it('runs the stamps counter: find by phone, add, hand over the free item', function () {
    [$guest, $program] = stampShop(needed: 2, cap: 5);
    $branch = Branch::factory()->create(['restaurant_id' => $guest->restaurant_id]);
    $this->actingAs(employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]));

    $screen = Livewire::test(StampCounter::class, ['branchId' => $branch->id])
        ->set('phone', '5555 1234')->call('find')
        ->assertSet('customerId', $guest->id)
        ->assertSee('Ice cream card')
        ->call('step', $program->id, 1)->call('step', $program->id, 1)
        ->call('add', $program->id)
        ->assertDispatched('ops-toast', tone: 'success');

    $wallet = WalletReward::withoutTenancy()->sole();
    $screen->assertSee('Free ice cream')->call('redeem', $wallet->id);

    expect($wallet->fresh()->status)->toBe(WalletReward::USED);

    Livewire::test(StampCounter::class, ['branchId' => $branch->id])
        ->set('phone', '5555 9999')->call('find')
        ->assertSet('customerId', Customer::withoutTenancy()->where('phone', '+97455559999')->value('id'));
});

it('keeps the stamps counter from kitchen staff', function () {
    [$guest] = stampShop();
    $branch = Branch::factory()->create(['restaurant_id' => $guest->restaurant_id]);
    $this->actingAs(employeeAt($guest->restaurant, 'kitchen', ['all_branches' => true]));

    Livewire::test(StampCounter::class, ['branchId' => $branch->id])->assertForbidden();
});

it('lets the owner add a stamp card, and keeps marketing-less staff out', function () {
    [$guest, , $iceCream] = stampShop();
    $reward = Reward::withoutTenancy()->sole();
    $this->actingAs(ownerOf($guest->restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($guest->restaurant);
    app(TenantContext::class)->set($guest->restaurant);

    Livewire::test(ManageStampPrograms::class)
        ->mountAction('addStampCard')
        ->fillForm([
            'name' => ['en' => 'Coffee card', 'ar' => 'بطاقة القهوة'],
            'target_type' => 'product', 'target_id' => $iceCream->id,
            'stamps_needed' => 8, 'reward_id' => $reward->id, 'daily_cap' => 2, 'is_active' => true,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(StampProgram::withoutTenancy()->where('stamps_needed', 8)->sole()->getTranslation('name', 'en'))->toBe('Coffee card');

    $this->actingAs(employeeAt($guest->restaurant, 'cashier', ['all_branches' => true]))->get('/app/karak/stamp-cards')->assertForbidden();
});

it('shows stamp cards to guests even without a points program', function () {
    [$guest, $program, $iceCream] = stampShop();
    app(Stamps::class)->earnForOrder(pastOrder($guest, 3000, now(), 'picked_up', [$iceCream->id, $iceCream->id]));
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $guest->restaurant_id, 'customer_id' => $guest->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$guest->restaurant_id}", $token);
    app()->forgetScopedInstances();

    Livewire::test(Rewards::class, ['restaurant' => $guest->restaurant])
        ->assertSee('Ice cream card')
        ->assertSee(trans_choice('guest.rewards.stamps_left', 3, ['count' => 3]))
        ->assertDontSee(__('guest.rewards.catalog'));
});

it('hides stamps when the restaurant has no Rewards service', function () {
    [$guest] = stampShop();
    $guest->restaurant->service(Service::Rewards)->forceFill(['status' => 'ended'])->save();

    $this->get(route('customer.rewards', $guest->restaurant))->assertNotFound();
    expect(app(Stamps::class)->earnForOrder(pastOrder($guest, 1500, now(), 'picked_up', [Product::withoutTenancy()->first()->id])))->toBe([]);
});

it('tells the guest at checkout what the bag does to their card', function () {
    [$guest, $program, $iceCream] = stampShop(needed: 3, cap: 5);
    $branch = app(CreateBranch::class)->handle($guest->restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $pricer = app(CartPricer::class);
    $stamps = app(Stamps::class);

    $one = $pricer->price($branch, [['key' => 'a', 'product_id' => $iceCream->id, 'quantity' => 1]]);
    expect($stamps->preview($guest->restaurant, $guest, $one->lines))->sequence(
        fn ($p) => $p->adds->toBe(1)->left->toBe(2)->fills->toBeFalse(),
    );

    $three = $pricer->price($branch, [['key' => 'a', 'product_id' => $iceCream->id, 'quantity' => 3]]);
    expect($stamps->preview($guest->restaurant, $guest, $three->lines)[0]['fills'])->toBeTrue();
});
