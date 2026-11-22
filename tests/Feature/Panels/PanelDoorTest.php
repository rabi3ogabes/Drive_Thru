<?php

use App\Models\User;

it('sends a signed-in platform admin from the restaurant workspace to the platform panel', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/app')->assertRedirect(url('/admin'));
});

it('sends a signed-in restaurant owner from the platform panel to their workspace', function () {
    $owner = ownerOf(restaurant(slug: 'tea-time'));

    $this->actingAs($owner)->get('/admin')->assertRedirect(url('/app'));
});

it('explains an account linked to nothing, with a sign-out button, instead of a bare 403', function (string $locale) {
    $nobody = User::factory()->create();

    $response = $this->actingAs($nobody)->withSession(['locale' => $locale])->get('/app');

    $response->assertForbidden()
        ->assertSee(__('errors.no_access.title', [], $locale))
        ->assertSee(__('errors.no_access.message', [], $locale))
        ->assertSee(__('errors.no_access.sign_out', [], $locale))
        ->assertSeeHtml('action="'.e(route('filament.app.auth.logout')).'"')
        ->assertSeeHtml('name="_token"')
        ->assertDontSee(__('errors.403.title', [], $locale));

    $this->actingAs($nobody)->get('/admin')->assertForbidden()->assertSee(__('errors.no_access.title', [], $locale));
})->with(['en', 'ar']);

it('lets an account linked to nothing sign out from that page', function () {
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->post(route('filament.app.auth.logout'))->assertRedirect();
    $this->assertGuest();
});

it('keeps deactivated accounts out of both panels', function () {
    $owner = ownerOf(restaurant(slug: 'tea-time'));
    $owner->forceFill(['is_active' => false])->save();

    $this->actingAs($owner)->get('/app')->assertForbidden();
    $this->actingAs($owner)->get('/admin')->assertForbidden();
});

it('still opens each panel for the right people', function () {
    $restaurant = restaurant(slug: 'tea-time');

    $this->actingAs(ownerOf($restaurant))->get('/app/tea-time')->assertOk();
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin')->assertOk();
});
