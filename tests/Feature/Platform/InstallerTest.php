<?php

use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Models\User;
use App\Support\Install\Installer;
use App\Support\Install\InstallProblem;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;

/** The installer with its files in a temp folder, and no real MySQL server. */
class TestInstaller extends Installer
{
    public string $dir;

    public ?InstallProblem $failConnect = null;

    public bool $migrated = false;

    public function connect(array $db): string
    {
        if ($this->failConnect) {
            throw $this->failConnect;
        }

        return 'mariadb';
    }

    public function migrate(): string
    {
        $this->migrated = true; // The test database is already migrated.

        return '';
    }

    public function keyPath(): string
    {
        return $this->dir.'/install-key.txt';
    }

    public function lockPath(): string
    {
        return $this->dir.'/installed.lock';
    }

    public function environmentPath(): string
    {
        return $this->dir.'/.env';
    }

    public function linkStorage(): bool
    {
        return true;
    }
}

beforeEach(function () {
    app()->setLocale('en');
    $this->installer = new TestInstaller;
    $this->installer->dir = storage_path('framework/testing/install-'.uniqid());
    File::ensureDirectoryExists($this->installer->dir);
    app()->instance(Installer::class, $this->installer);
});

afterEach(function () {
    File::deleteDirectory($this->installer->dir);
});

/** @return array<string, string> */
function installForm(array $overrides = []): array
{
    return array_merge([
        'key' => test()->installer->setupKey(),
        'db_host' => 'localhost',
        'db_port' => '3306',
        'db_database' => 'u123456789_drivethru',
        'db_username' => 'u123456789_drivethru',
        'db_password' => 'Pa$$w0rd\'with"quotes',
        'name' => 'Rabii Souai',
        'email' => 'Owner@Example.com',
        'password' => 'Str0ngPassw0rd',
        'password_confirmation' => 'Str0ngPassw0rd',
    ], $overrides);
}

it('shows the installer before installing and writes a setup key only the server can read', function () {
    $this->get('/install')->assertOk()
        ->assertSee(__('install.title'))
        ->assertSee(__('install.steps.database'))
        ->assertSee('install-key.txt');

    expect(strlen($this->installer->setupKey()))->toBe(32);

    $this->get('/install?lang=ar')->assertOk()->assertSee(__('install.title', [], 'ar'));
});

it('refuses a wrong setup key and writes nothing', function () {
    $this->post('/install', installForm(['key' => 'not-the-key']))
        ->assertStatus(422)
        ->assertSee(__('install.errors.key'));

    expect(is_file($this->installer->environmentPath()))->toBeFalse()
        ->and($this->installer->isInstalled())->toBeFalse()
        ->and(User::query()->where('is_super_admin', true)->exists())->toBeFalse();
});

it('installs: saves the database details exactly, creates the super admin, then locks itself for good', function () {
    File::put($this->installer->environmentPath(), "APP_NAME=\"Drive-Thru Plus\"\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nDB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\nMAIL_MAILER=log\n");

    $this->post('/install', installForm())->assertOk()
        ->assertSee(__('install.done.title'))
        ->assertSee('owner@example.com');

    $env = Dotenv::parse(File::get($this->installer->environmentPath()));
    expect($env)->toMatchArray([
        'APP_NAME' => 'Drive-Thru Plus',
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'DB_CONNECTION' => 'mariadb',
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'u123456789_drivethru',
        'DB_USERNAME' => 'u123456789_drivethru',
        'DB_PASSWORD' => 'Pa$$w0rd\'with"quotes',
        'MAIL_MAILER' => 'log',
    ])->and($env['APP_KEY'])->toStartWith('base64:')
        ->and(strlen(base64_decode(substr($env['APP_KEY'], 7))))->toBe(32);

    $admin = User::query()->where('email', 'owner@example.com')->sole();
    expect($admin->is_super_admin)->toBeTrue()
        ->and($admin->is_active)->toBeTrue()
        ->and($this->installer->migrated)->toBeTrue()
        ->and($this->installer->isInstalled())->toBeTrue()
        ->and(is_file($this->installer->keyPath()))->toBeFalse();

    $this->get('/install')->assertNotFound();
    $this->post('/install', installForm())->assertNotFound();
});

it('keeps an app key that already exists', function () {
    File::put($this->installer->environmentPath(), "APP_KEY=base64:c2VjcmV0c2VjcmV0c2VjcmV0c2VjcmV0c2VjcmV0MTI=\n");

    $this->post('/install', installForm())->assertOk();

    expect(Dotenv::parse(File::get($this->installer->environmentPath()))['APP_KEY'])->toBe('base64:c2VjcmV0c2VjcmV0c2VjcmV0c2VjcmV0c2VjcmV0MTI=');
});

it('explains a database that won’t connect, and stays open to try again', function () {
    $this->installer->failConnect = InstallProblem::because('database', ['reason' => 'access_denied']);

    $this->post('/install', installForm())->assertStatus(422)
        ->assertSee(__('install.reasons.access_denied'));

    expect($this->installer->isInstalled())->toBeFalse()
        ->and(is_file($this->installer->environmentPath()))->toBeFalse()
        ->and(is_file($this->installer->keyPath()))->toBeTrue();
});

it('asks for a strong admin password and keeps what was typed (never the passwords)', function () {
    $this->post('/install', installForm(['password' => 'weak', 'password_confirmation' => 'weak']))
        ->assertStatus(422)
        ->assertSee('u123456789_drivethru')
        ->assertDontSee('Pa$$w0rd', false);

    expect($this->installer->isInstalled())->toBeFalse();
});

it('keeps /install out of the restaurant addresses', function () {
    expect(preg_match('#^'.CreateRestaurant::publicSlugPattern().'$#', 'install'))->toBe(0);
});
