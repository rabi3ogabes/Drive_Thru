<?php

use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\Integrations;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function intoIntegrations(Restaurant $restaurant, User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);
}

it('saves SADAD keys encrypted and write-only, and never shows them again', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    intoIntegrations($restaurant, $owner);

    Livewire::test(Integrations::class)
        ->set('data.payments.provider', 'sadad')
        ->set('data.payments.mode', 'test')
        ->set('data.payments.enabled', true)
        ->set('data.payments.merchant_id', '7015085')
        ->set('data.payments.website', 'masar.test')
        ->set('data.payments.secret_key_test', 'super-secret-test')
        ->set('data.payments.api_key_test', 'api-secret')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDontSee('super-secret-test')
        ->assertSee(__('integrations.saved_secret'));

    $integration = Integration::withoutTenancy()->where('kind', IntegrationKind::Payments->value)->sole();
    $raw = DB::table('integrations')->where('id', $integration->id)->value('credentials');

    expect($integration->credential('secret_key_test'))->toBe('super-secret-test')
        ->and($raw)->not->toContain('super-secret-test')
        ->and($integration->toArray())->not->toHaveKey('credentials');

    // Saving again with empty key fields keeps the saved keys.
    Livewire::test(Integrations::class)->set('data.payments.website', 'www.masar.test')->call('save');
    expect($integration->fresh()->credential('secret_key_test'))->toBe('super-secret-test')
        ->and($integration->fresh()->credential('website'))->toBe('www.masar.test');

    $audit = DB::table('activity_log')->where('event', 'integration.saved')->latest('id')->value('properties');
    expect($audit)->not->toContain('super-secret-test')->and($audit)->toContain('keys_changed');
});

it('tests the SADAD connection from settings', function () {
    Http::fake(['api-sandbox.sadad.qa/api/userbusinesses/login' => Http::response(['accessToken' => 'T'])]);
    $restaurant = restaurant();
    intoIntegrations($restaurant, ownerOf($restaurant));

    Livewire::test(Integrations::class)
        ->set('data.payments.provider', 'sadad')
        ->set('data.payments.api_sadad_id', '9288463')
        ->set('data.payments.api_domain', 'masar.test')
        ->set('data.payments.api_key_test', 'api-secret')
        ->call('save')
        ->call('testSadad')
        ->assertNotified(__('integrations.connection_ok'));

    expect(Integration::withoutTenancy()->where('kind', 'payments')->sole()->verified_at)->not->toBeNull();
});

it('keeps integrations to people who manage settings', function () {
    $restaurant = restaurant();
    intoIntegrations($restaurant, employeeAt($restaurant, 'cashier'));

    expect(Integrations::canAccess())->toBeFalse();
    $this->get(Integrations::getUrl(tenant: $restaurant))->assertForbidden();
});
