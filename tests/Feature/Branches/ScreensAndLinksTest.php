<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Actions\IssuePairingCode;
use App\Domain\Branches\Actions\RevokeDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Http\Middleware\AuthenticateDevice;

/**
 * Pairs through the real endpoint and returns the plain device token from the
 * encrypted cookie; withCookie() then encrypts it again like a browser sends it.
 */
function newBranch(?Restaurant $restaurant = null, string $name = 'Lusail'): Branch
{
    return app(CreateBranch::class)->handle($restaurant ?? restaurant(), ['name' => ['en' => $name, 'ar' => $name]]);
}

it('pairs a screen once with a one-time code and keeps it signed in', function () {
    $branch = newBranch();
    $code = app(CreateDevice::class)->handle($branch, DeviceKind::Kitchen, 'Grill tablet');

    $response = $this->post(route('screen.pair.store'), ['code' => $code->formatted()]);

    $response->assertRedirect(route('screen.show'));
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === AuthenticateDevice::COOKIE);
    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addYears(4)->timestamp);

    $device = $code->device->fresh();
    expect($device->isPaired())->toBeTrue()
        ->and($device->pairing_code_hash)->toBeNull();

    // The code is burned: a second screen can't reuse it.
    $this->post(route('screen.pair.store'), ['code' => $code->code])->assertSessionHasErrors('code');
});

it('rejects expired and unknown codes', function () {
    $code = app(CreateDevice::class)->handle(newBranch(), DeviceKind::Tv, 'Lobby TV');
    $this->travel(16)->minutes();

    $this->post(route('screen.pair.store'), ['code' => $code->code])->assertSessionHasErrors('code');
    $this->post(route('screen.pair.store'), ['code' => '000000'])->assertSessionHasErrors('code');
});

it('shows the paired screen its branch and answers heartbeats', function () {
    $branch = newBranch();
    $code = app(CreateDevice::class)->handle($branch, DeviceKind::Cashier, 'Window 1');
    $token = pairedToken($this, $code->code);

    $this->withCookie(AuthenticateDevice::COOKIE, $token)
        ->get(route('screen.show'))
        ->assertOk()
        ->assertSee('Lusail')
        ->assertSee(__('devices.kind.cashier.label'));

    $this->withCookie(AuthenticateDevice::COOKIE, $token)
        ->withCredentials()->getJson(route('screen.heartbeat'))
        ->assertOk()
        ->assertJson(['paired' => true, 'state' => 'open']);
});

it('signs a revoked screen out on its next heartbeat', function () {
    $code = app(CreateDevice::class)->handle(newBranch(), DeviceKind::Pickup, 'Pickup');
    $token = pairedToken($this, $code->code);

    $this->withCookie(AuthenticateDevice::COOKIE, $token)->withCredentials()->getJson(route('screen.heartbeat'))->assertOk();

    app(RevokeDevice::class)->handle($code->device->fresh());

    $this->withCookie(AuthenticateDevice::COOKIE, $token)->withCredentials()->getJson(route('screen.heartbeat'))->assertUnauthorized();
    $this->withCookie(AuthenticateDevice::COOKIE, $token)->get(route('screen.show'))->assertRedirect(route('screen.pair'));
});

it('re-pairing a screen replaces its old sign-in', function () {
    $code = app(CreateDevice::class)->handle(newBranch(), DeviceKind::Kitchen, 'Kitchen');
    $old = pairedToken($this, $code->code);

    $this->withCookie(AuthenticateDevice::COOKIE, $old)->withCredentials()->getJson(route('screen.heartbeat'))->assertOk();

    $fresh = app(IssuePairingCode::class)->handle($code->device->fresh());
    $this->post(route('screen.pair.store'), ['code' => $fresh->code]);

    $this->withCookie(AuthenticateDevice::COOKIE, $old)->withCredentials()->getJson(route('screen.heartbeat'))->assertUnauthorized();
});

it('keeps a screen inside its own restaurant', function () {
    $a = newBranch(restaurant(['en' => 'Alpha', 'ar' => 'ألفا']), 'Alpha One');
    $b = newBranch(restaurant(['en' => 'Beta', 'ar' => 'بيتا']), 'Beta One');
    $code = app(CreateDevice::class)->handle($a, DeviceKind::Kitchen, 'Alpha kitchen');
    $token = pairedToken($this, $code->code);

    $this->withCookie(AuthenticateDevice::COOKIE, $token)->get(route('screen.show'))
        ->assertSee('Alpha One')
        ->assertDontSee('Beta One');

    expect(Device::withoutTenancy()->where('branch_id', $b->id)->exists())->toBeFalse();
});

it('serves restaurant and branch pages with live status', function () {
    $restaurant = restaurant(slug: 'masar');
    $branch = newBranch($restaurant, 'Lusail Marina');

    $this->get('/masar')->assertOk()->assertSee('Lusail Marina')->assertSee(__('branches.state.open'));
    $this->get("/masar/{$branch->code}")->assertOk()->assertSee('Lusail Marina')->assertSee(__('public.branch.hours'));
});

it('shows friendly pages for unknown restaurants, unknown branches and paused accounts', function () {
    $restaurant = restaurant(slug: 'masar');

    $this->get('/no-such-place')->assertNotFound()->assertSee(__('public.missing.restaurant_title'));
    $this->get('/masar/nowhere')->assertNotFound()->assertSee(__('public.missing.branch_title'));

    $restaurant->forceFill(['status' => RestaurantStatus::Suspended])->save();
    $this->get('/masar')->assertStatus(503)->assertSee(__('public.unavailable.title'));
});

it('never lets a restaurant link shadow platform paths', function () {
    $this->get('/admin/login')->assertOk()->assertDontSee(__('public.missing.restaurant_title'));
    $this->get('/pair')->assertOk()->assertSee(__('devices.pair.heading'));
});

it('downloads QR codes for team members only, and ticks the setup step', function () {
    $restaurant = restaurant(slug: 'masar');
    $branch = newBranch($restaurant);
    $owner = ownerOf($restaurant);
    $stranger = ownerOf(restaurant(['en' => 'Other', 'ar' => 'آخر']));

    $this->actingAs($owner)->get(route('qr.download', ['restaurant' => 'masar', 'format' => 'svg']))
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->actingAs($owner)->get(route('qr.download', ['restaurant' => 'masar', 'format' => 'png', 'branch' => $branch->code]))
        ->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($owner)->get(route('qr.download', ['restaurant' => 'masar', 'format' => 'pdf', 'branch' => $branch->code]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($restaurant->fresh()->settings['onboarding']['qr_downloaded_at'] ?? null)->not->toBeNull();

    $this->actingAs($stranger)->get(route('qr.download', ['restaurant' => 'masar', 'format' => 'svg']))->assertForbidden();
});
