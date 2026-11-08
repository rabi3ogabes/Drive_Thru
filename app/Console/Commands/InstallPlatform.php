<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\PlanCatalog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates (or promotes) the platform super admin. The only way to become a
 * super admin: there is deliberately no button for it anywhere in the UI.
 */
final class InstallPlatform extends Command
{
    protected $signature = 'platform:install
        {--name= : Full name}
        {--email= : Email address}
        {--password= : Password (prompted securely when omitted)}';

    protected $description = 'Create the platform super admin account';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('Email', required: true, validate: fn (string $v): ?string => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.');
        $password = $this->option('password') ?: $this->askPasswordTwice();

        if ($password === null) {
            $this->components->error('The two passwords do not match. Nothing was changed.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'password' => ['required', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => mb_strtolower(trim((string) $email))]);
        $existed = $user->exists;

        $user->forceFill([
            'name' => trim((string) $name),
            'password' => $password,
            'is_super_admin' => true,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // A new platform starts with the standard plan tiers (kept as they are if already there).
        app(PlanCatalog::class)->install();

        activity()->performedOn($user)->event('platform.installed')->log($existed ? 'Promoted to super admin' : 'Super admin created');

        $this->components->info($existed
            ? "{$user->email} is now the platform super admin."
            : "Super admin {$user->email} created. Sign in at ".url('/admin'));

        return self::SUCCESS;
    }

    /** Typed blind, so typed twice: a typo here locks the owner out. Null when they differ. */
    private function askPasswordTwice(): ?string
    {
        $password = password('Password', required: true, hint: 'At least 10 characters, upper and lower case letters and a number.');

        return password('Password again', required: true) === $password ? $password : null;
    }
}
