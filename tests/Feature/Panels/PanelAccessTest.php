<?php

use App\Domain\Access\Models\PlatformRole;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Models\User;

it('sends guests to the sign-in page', function (string $path, string $login) {
    $this->get($path)->assertRedirect($login);
})->with([
    'platform' => ['/admin', '/admin/login'],
    'restaurant' => ['/app', '/app/login'],
]);

it('renders both sign-in pages in Arabic and English', function (string $locale, string $dir, string $heading) {
    $this->withHeader('Accept-Language', $locale)
        ->get('/admin/login')
        ->assertOk()
        ->assertSee("dir=\"{$dir}\"", false)
        ->assertSee($heading);

    $this->get('/app/login')->assertOk();
})->with([
    'arabic' => ['ar', 'rtl', 'أهلًا بعودتك'],
    'english' => ['en', 'ltr', 'Welcome back'],
]);

it('opens the platform console for the super admin', function () {
    $admin = User::factory()->superAdmin()->create(['name' => 'Noor Al-Thani']);

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('Noor');
});

it('opens the platform console for platform staff', function () {
    $staff = User::factory()->create();
    $staff->forceFill(['platform_role_id' => PlatformRole::factory()->create()->id])->save();

    $this->actingAs($staff)->get('/admin')->assertOk();
});

it('keeps restaurant staff out of the platform console', function () {
    $owner = ownerOf(restaurant());

    $this->actingAs($owner)->get('/admin')->assertForbidden();
});

it('opens the restaurant workspace for its team', function () {
    $restaurant = restaurant(slug: 'masar');
    $cashier = employeeAt($restaurant, 'cashier');

    $this->actingAs($cashier)->get('/app')->assertRedirect('/app/masar');
    $this->actingAs($cashier)->get('/app/masar')->assertOk();
});

it('never lets a team member into another restaurant', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار'], slug: 'masar');
    restaurant(['en' => 'Sahra', 'ar' => 'صحراء'], slug: 'sahra');
    $manager = employeeAt($masar, 'general_manager');

    $this->actingAs($manager)->get('/app/sahra')->assertNotFound();
});

it('refuses the restaurant workspace without an active membership', function (array $attributes) {
    $restaurant = restaurant(slug: 'masar');
    $user = employeeAt($restaurant, 'cashier', $attributes);

    $this->actingAs($user)->get('/app/masar')->assertForbidden();
})->with([
    'suspended' => [['status' => EmployeeStatus::Suspended]],
    'invited, not yet joined' => [['status' => EmployeeStatus::Invited]],
]);

it('refuses deactivated accounts everywhere', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    $owner->forceFill(['is_active' => false])->save();

    $this->actingAs($owner)->get('/app/masar')->assertForbidden();
});

it('lists the account sessions page', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/admin/account/sessions')
        ->assertOk()
        ->assertSee(__('security.sessions.title'));
});
