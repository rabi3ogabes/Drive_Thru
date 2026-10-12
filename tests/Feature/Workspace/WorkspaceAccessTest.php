<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function workspaceBranch(Restaurant $restaurant, string $name): Branch
{
    return app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => $name, 'ar' => $name]]);
}

/** A team member in a built-in position, working in the given branches. */
function memberAt(Restaurant $restaurant, string $position, Branch ...$branches): User
{
    $user = employeeAt($restaurant, $position);
    $employment = Employee::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('user_id', $user->id)->sole();
    $employment->branches()->sync(array_map(fn (Branch $branch): int => $branch->id, $branches));

    return $user->fresh();
}

/*
 * The MASTER_PROMPT §8 matrix for Phase 2 pages. true = allowed, false = refused.
 */
dataset('positions', [
    'owner' => ['owner', ['branches' => true, 'screens' => true, 'links' => true, 'team' => true, 'positions' => true, 'settings' => true, 'billing' => true]],
    'general manager' => ['general_manager', ['branches' => true, 'screens' => true, 'links' => true, 'team' => true, 'positions' => true, 'settings' => false, 'billing' => false]],
    'branch manager' => ['branch_manager', ['branches' => true, 'screens' => true, 'links' => true, 'team' => true, 'positions' => true, 'settings' => false, 'billing' => false]],
    'cashier' => ['cashier', ['branches' => false, 'screens' => false, 'links' => false, 'team' => false, 'positions' => false, 'settings' => false, 'billing' => false]],
    'kitchen' => ['kitchen', ['branches' => false, 'screens' => false, 'links' => false, 'team' => false, 'positions' => false, 'settings' => false, 'billing' => false]],
    'expeditor' => ['expeditor', ['branches' => false, 'screens' => false, 'links' => false, 'team' => false, 'positions' => false, 'settings' => false, 'billing' => false]],
    'accountant' => ['accountant', ['branches' => false, 'screens' => false, 'links' => false, 'team' => false, 'positions' => false, 'settings' => false, 'billing' => false]],
    'marketing' => ['marketing', ['branches' => false, 'screens' => false, 'links' => false, 'team' => false, 'positions' => false, 'settings' => false, 'billing' => false]],
]);

it('shows each position exactly its workspace pages and menu items', function (string $position, array $expected) {
    $restaurant = restaurant(slug: 'masar');
    $branch = workspaceBranch($restaurant, 'Lusail');
    $user = $position === 'owner' ? ownerOf($restaurant) : memberAt($restaurant, $position, $branch);

    $dashboard = $this->actingAs($user)->withHeader('Accept-Language', 'en')->get('/app/masar')->assertOk();

    foreach ($expected as $page => $allowed) {
        $response = $this->actingAs($user)->get("/app/masar/{$page}");
        $allowed ? $response->assertOk() : $response->assertForbidden();

        $label = match ($page) {
            'branches' => __('branches.plural', [], 'en'),
            'screens' => __('devices.plural', [], 'en'),
            'links' => __('links.title', [], 'en'),
            'team' => __('staff.nav', [], 'en'),
            'positions' => __('positions.plural', [], 'en'),
            'settings' => __('settings.title', [], 'en'),
            'billing' => __('billing.owner.title', [], 'en'),
        };

        $allowed
            ? $dashboard->assertSee("/app/masar/{$page}", false)
            : $dashboard->assertDontSee("href=\"http://localhost/app/masar/{$page}\"", false);

        expect($label)->not->toBeEmpty();
    }
})->with('positions');

it('keeps a branch manager inside their own branches', function () {
    $restaurant = restaurant(slug: 'masar');
    $mine = workspaceBranch($restaurant, 'Lusail Marina');
    $theirs = workspaceBranch($restaurant, 'Wakra Corniche');
    $manager = memberAt($restaurant, 'branch_manager', $mine);

    $this->actingAs($manager)->get('/app/masar/branches')
        ->assertOk()
        ->assertSee('Lusail Marina')
        ->assertDontSee('Wakra Corniche');

    $this->actingAs($manager)->get('/app/masar/links')
        ->assertOk()
        ->assertSee($mine->code)
        ->assertDontSee($theirs->code);
});

it('never shows one restaurant’s branches, team or screens to another', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار'], slug: 'masar');
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء'], slug: 'sahra');
    workspaceBranch($masar, 'Masar Lusail');
    workspaceBranch($sahra, 'Sahra Pearl');
    $sahraOwner = ownerOf($sahra);

    foreach (['branches', 'team', 'screens', 'positions', 'settings', 'links'] as $page) {
        $this->actingAs($sahraOwner)->get("/app/masar/{$page}")->assertNotFound();
    }

    $this->actingAs($sahraOwner)->get('/app/sahra/branches')
        ->assertOk()
        ->assertSee('Sahra Pearl')
        ->assertDontSee('Masar Lusail');
});
