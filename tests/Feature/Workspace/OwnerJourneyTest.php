<?php

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\Position;
use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Billing\Models\Plan;
use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Enums\BranchState;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Enums\MenuLinkMode;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\BranchHour;
use App\Domain\Branches\Models\Device;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\SetupChecklist;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\RestaurantSettings;
use App\Filament\App\Resources\Branches\Pages\ManageBranches;
use App\Filament\App\Resources\Positions\Pages\ManagePositions;
use App\Filament\App\Resources\Screens\Pages\ManageScreens;
use App\Filament\App\Resources\Team\Pages\ManageTeam;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** Signs in and enters the restaurant workspace, like opening /app/{slug}. */
function enterWorkspace(Restaurant $restaurant, User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);
}

beforeEach(fn () => Notification::fake());

it('takes an owner from an empty account to branches, a team and a paired kitchen screen', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    enterWorkspace($restaurant, $owner);

    // Two branches, the first with split-shift hours.
    Livewire::test(ManageBranches::class)
        ->callAction('create', data: [
            'name' => ['en' => 'Lusail Marina', 'ar' => 'لوسيل مارينا'],
            'avg_prep_minutes' => 9,
            'hours' => [
                ['weekday' => 0, 'opens_at' => '07:00', 'closes_at' => '11:00'],
                ['weekday' => 0, 'opens_at' => '16:00', 'closes_at' => '02:00'],
            ],
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ManageBranches::class)
        ->callAction('create', data: ['name' => ['en' => 'Wakra Corniche', 'ar' => 'كورنيش الوكرة'], 'avg_prep_minutes' => 12])
        ->assertHasNoActionErrors();

    $lusail = Branch::query()->where('code', 'lusail-marina')->sole();
    $wakra = Branch::query()->where('code', 'wakra-corniche')->sole();
    expect($lusail->hours)->toHaveCount(2)
        ->and($lusail->lanes)->toHaveCount(1);

    // Three people, three positions.
    foreach ([['Hana', 'hana@example.com', 'branch_manager', [$lusail->id]], ['Omar', 'omar@example.com', 'cashier', [$lusail->id, $wakra->id]], ['Ali', 'ali@example.com', 'kitchen', [$wakra->id]]] as [$name, $email, $key, $branches]) {
        Livewire::test(ManageTeam::class)
            ->callAction('invite', data: [
                'name' => $name,
                'email' => $email,
                'position_id' => Position::query()->where('key', $key)->value('id'),
                'branch_ids' => $branches,
            ])
            ->assertHasNoActionErrors();
    }

    expect(Employee::query()->where('status', EmployeeStatus::Invited)->count())->toBe(3);

    // A kitchen tablet, paired with the code the owner is shown.
    $page = Livewire::test(ManageScreens::class)
        ->callAction('addScreen', data: ['branch_id' => $wakra->id, 'kind' => DeviceKind::Kitchen->value, 'name' => 'Grill tablet'])
        ->assertActionMounted('pairingCode');

    $code = $page->get('mountedActions')[0]['arguments']['code'];
    $this->post(route('screen.pair.store'), ['code' => $code])->assertRedirect(route('screen.show'));

    expect(Device::query()->sole()->isPaired())->toBeTrue()
        ->and(app(SetupChecklist::class)->steps($restaurant->fresh()))->toMatchArray(['branch' => true, 'hours' => true, 'team' => true, 'screen' => true]);
});

it('pauses and resumes a branch from the live controls', function () {
    $restaurant = restaurant(slug: 'masar');
    enterWorkspace($restaurant, ownerOf($restaurant));
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);

    Livewire::test(ManageBranches::class)
        ->callAction(TestAction::make('pause')->table($branch), data: ['minutes' => '30'])
        ->assertHasNoActionErrors();

    expect($branch->fresh()->state())->toBe(BranchState::Paused);

    Livewire::test(ManageBranches::class)->callAction(TestAction::make('resume')->table($branch));

    expect($branch->fresh()->state())->toBe(BranchState::Open);
});

it('shows the friendly upgrade prompt instead of adding a branch over the plan', function () {
    $restaurant = restaurant(slug: 'masar');
    $plan = Plan::factory()->create(['limits' => ['branches' => 1, 'employees' => 5]]);
    app(StartSubscription::class)->handle($restaurant, $plan);
    enterWorkspace($restaurant, ownerOf($restaurant));
    app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Only', 'ar' => 'الوحيد']]);

    Livewire::test(ManageBranches::class)
        ->assertActionHidden('create')
        ->assertActionVisible('limitReached');
});

it('builds a custom position from grouped permissions', function () {
    $restaurant = restaurant(slug: 'masar');
    enterWorkspace($restaurant, ownerOf($restaurant));

    Livewire::test(ManagePositions::class)
        ->callAction('create', data: [
            'name' => ['en' => 'Drive-thru host', 'ar' => 'مضيف الدرايف ثرو'],
            'description' => ['en' => 'Greets cars and hands over orders.', 'ar' => 'يستقبل السيارات ويسلّم الطلبات.'],
            'permission_groups' => ['operations' => [RestaurantPermission::ViewOrders->value, RestaurantPermission::OperatePickup->value]],
        ])
        ->assertHasNoActionErrors();

    $position = Position::query()->where('is_system', false)->sole();
    expect($position->getTranslation('name', 'ar'))->toBe('مضيف الدرايف ثرو')
        ->and($position->permissions->map->value->all())->toEqualCanonicalizing(['orders.view', 'pickup.operate']);
});

it('saves restaurant settings in minor units and basis points', function () {
    $restaurant = restaurant(slug: 'masar');
    enterWorkspace($restaurant, ownerOf($restaurant));

    Livewire::test(RestaurantSettings::class)
        ->fillForm([
            'tax_rate_percent' => 5,
            'service_fee_percent' => 2.5,
            'menu_link_mode' => MenuLinkMode::PerBranch->value,
            'cancellation_window_minutes' => 3,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $restaurant->refresh();
    expect($restaurant->tax_rate_bp)->toBe(500)
        ->and($restaurant->service_fee_bp)->toBe(250)
        ->and($restaurant->menu_link_mode)->toBe(MenuLinkMode::PerBranch);
});

it('refuses the settings page to anyone without the settings permission', function () {
    $restaurant = restaurant(slug: 'masar');
    enterWorkspace($restaurant, employeeAt($restaurant, 'general_manager'));

    Livewire::test(RestaurantSettings::class)->assertForbidden();
});

it('shows opening hours exactly as stored, without shifting time zones', function () {
    $restaurant = restaurant(slug: 'masar');
    enterWorkspace($restaurant, ownerOf($restaurant));
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    BranchHour::query()->create(['branch_id' => $branch->id, 'weekday' => 0, 'opens_at' => '07:00', 'closes_at' => '02:00']);

    $page = Livewire::test(ManageBranches::class)->mountAction(TestAction::make('edit')->table($branch));
    $shift = collect($page->get('mountedActions')[0]['data']['hours'])->first();

    expect(substr((string) $shift['opens_at'], 0, 5))->toBe('07:00')
        ->and(substr((string) $shift['closes_at'], 0, 5))->toBe('02:00');

    $page->callMountedAction()->assertHasNoActionErrors();

    expect($branch->hours()->first()?->opens_at)->toBe('07:00');
});
