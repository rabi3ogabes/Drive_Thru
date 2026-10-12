<?php

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Hash;

it('creates the platform super admin', function () {
    $this->artisan('platform:install', ['--name' => 'Noor Al-Thani', '--email' => 'Noor@Example.com', '--password' => 'a-strong-passphrase'])
        ->assertSuccessful();

    $admin = User::where('email', 'noor@example.com')->sole();

    expect($admin->isSuperAdmin())->toBeTrue()
        ->and(Hash::check('a-strong-passphrase', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();
});

it('promotes an existing account instead of duplicating it', function () {
    $user = User::factory()->create(['email' => 'noor@example.com']);

    $this->artisan('platform:install', ['--name' => 'Noor', '--email' => 'noor@example.com', '--password' => 'a-strong-passphrase'])
        ->assertSuccessful();

    expect(User::count())->toBe(1)->and($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('rejects invalid input', function () {
    $this->artisan('platform:install', ['--name' => 'Noor', '--email' => 'not-an-email', '--password' => 'short'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('is the only way to become a super admin', function () {
    $user = User::factory()->create();
    $user->fill(['is_super_admin' => true])->save();

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
})->throws(MassAssignmentException::class);
