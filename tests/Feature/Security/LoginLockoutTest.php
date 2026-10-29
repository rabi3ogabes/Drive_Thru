<?php

use App\Domain\Security\Enums\SecurityAlertType;
use App\Domain\Security\LoginThrottle;
use App\Domain\Security\Models\SecurityAlert;
use App\Filament\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/** Each Livewire login attempt also hits Filament's own per-minute limiter; clear it between attempts. */
function attemptLogin(string $email, string $password): Testable
{
    RateLimiter::clear('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1'));

    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');
}

it('signs a platform admin in and records the sign-in', function () {
    $admin = User::factory()->superAdmin()->create(['password' => 'correct-horse']);

    attemptLogin($admin->email, 'correct-horse')->assertHasNoFormErrors();

    expect(auth()->id())->toBe($admin->id)
        ->and($admin->fresh()->last_login_at)->not->toBeNull()
        ->and($admin->fresh()->last_login_ip)->toBe('127.0.0.1');
});

it('locks an account after five failures and refuses even the right password', function () {
    $admin = User::factory()->superAdmin()->create(['password' => 'correct-horse']);

    foreach (range(1, 5) as $_) {
        attemptLogin($admin->email, 'wrong-password')->assertHasFormErrors(['email']);
    }

    attemptLogin($admin->email, 'correct-horse')->assertHasErrors(['data.email']);

    expect(auth()->check())->toBeFalse()
        ->and(app(LoginThrottle::class)->lockedUntil($admin->email, '127.0.0.1'))->not->toBeNull();
});

it('raises one security alert and notifies super admins when an account locks', function () {
    $watcher = User::factory()->superAdmin()->create();
    $target = User::factory()->create(['email' => 'khalid@masar.test']);

    foreach (range(1, 5) as $_) {
        attemptLogin($target->email, 'nope');
    }

    $alert = SecurityAlert::sole();

    expect($alert->type)->toBe(SecurityAlertType::AccountLocked)
        ->and($alert->attempted_email)->toBe('kh***@masar.test')
        ->and($alert->attempt_count)->toBe(5)
        ->and($watcher->notifications()->count())->toBe(1);
});

it('never stores raw emails or IPs as throttle keys', function () {
    attemptLogin('someone@example.com', 'nope');

    $principals = DB::table('login_security_states')->pluck('principal')->implode(' ');

    expect($principals)->not->toContain('someone@example.com')->not->toContain('127.0.0.1');
});

it('forgets old failures once the window has passed', function () {
    $user = User::factory()->superAdmin()->create(['password' => 'correct-horse']);

    foreach (range(1, 4) as $_) {
        attemptLogin($user->email, 'nope');
    }

    $this->travel(16)->minutes();

    attemptLogin($user->email, 'nope');
    attemptLogin($user->email, 'correct-horse')->assertHasNoFormErrors();

    expect(auth()->id())->toBe($user->id);
});

it('unlocks automatically after the lock period', function () {
    $user = User::factory()->superAdmin()->create(['password' => 'correct-horse']);

    foreach (range(1, 5) as $_) {
        attemptLogin($user->email, 'nope');
    }

    $this->travel(31)->minutes();

    attemptLogin($user->email, 'correct-horse')->assertHasNoFormErrors();
    expect(auth()->id())->toBe($user->id);
});

it('clears earlier failures after a successful sign-in', function () {
    $user = User::factory()->superAdmin()->create(['password' => 'correct-horse']);

    foreach (range(1, 4) as $_) {
        attemptLogin($user->email, 'nope');
    }

    attemptLogin($user->email, 'correct-horse');
    auth()->logout();

    expect(DB::table('login_security_states')->count())->toBe(0);
});

it('blocks a network that sprays many accounts', function () {
    config(['security.login.max_ip_failures' => 6]);

    foreach (range(1, 6) as $i) {
        attemptLogin("victim{$i}@example.com", 'nope');
    }

    expect(SecurityAlert::where('type', SecurityAlertType::IpLocked)->count())->toBe(1)
        ->and(app(LoginThrottle::class)->lockedUntil('fresh@example.com', '127.0.0.1'))->not->toBeNull();
});

it('refuses deactivated accounts with the same generic error', function () {
    $user = User::factory()->superAdmin()->inactive()->create(['password' => 'correct-horse']);

    attemptLogin($user->email, 'correct-horse')->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse();
});

it('tells a platform admin on the restaurant sign-in where to go, only with the right password', function () {
    $admin = User::factory()->superAdmin()->create(['password' => 'correct-horse']);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    attemptLogin($admin->email, 'correct-horse')
        ->assertHasErrors(['data.email'])
        ->assertSee(Filament::getPanel('admin')->getLoginUrl());
    expect(auth()->check())->toBeFalse();

    attemptLogin($admin->email, 'wrong-password')
        ->assertHasErrors(['data.email'])
        ->assertDontSee(Filament::getPanel('admin')->getLoginUrl());
});

it('tells a restaurant owner on the platform sign-in where to go', function () {
    $owner = ownerOf(restaurant());
    $owner->forceFill(['password' => 'correct-horse'])->save();

    attemptLogin($owner->email, 'correct-horse')
        ->assertHasErrors(['data.email'])
        ->assertSee(Filament::getPanel('app')->getLoginUrl());
    expect(auth()->check())->toBeFalse();
});
