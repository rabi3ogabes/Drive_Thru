<?php

declare(strict_types=1);

namespace App\Support\Install;

use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Throwable;

/**
 * The first install from the browser (/install), for hosts without SSH.
 *
 * Only someone who can read the server's files can use it: the page asks
 * for a random setup key that it writes to storage/app/install-key.txt on
 * the first visit. It checks the server, tests the database, writes the
 * database details and production settings into .env, creates the tables
 * and the super admin, then locks itself for good (installed.lock) and
 * deletes the key.
 */
class Installer
{
    public const MIN_PHP = '8.3.0';

    /** PHP extensions the app needs on the server. */
    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'intl', 'gd', 'fileinfo', 'curl', 'dom', 'xml', 'zip', 'bcmath'];

    public function isInstalled(): bool
    {
        return is_file($this->lockPath());
    }

    /** The key the page asks for, created on first use. */
    public function setupKey(): string
    {
        $path = $this->keyPath();

        if (! is_file($path)) {
            @mkdir(dirname($path), 0755, true);
            file_put_contents($path, Str::random(32).PHP_EOL);
            @chmod($path, 0600);
        }

        return trim((string) file_get_contents($path));
    }

    public function keyMatches(string $given): bool
    {
        return is_file($this->keyPath()) && hash_equals($this->setupKey(), trim($given));
    }

    /**
     * @return list<array{key: string, label: string, ok: bool, detail: string}>
     */
    public function requirements(): array
    {
        $checks = [[
            'key' => 'php',
            'label' => 'PHP '.self::MIN_PHP,
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => PHP_VERSION,
        ]];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = ['key' => 'ext', 'label' => $extension, 'ok' => extension_loaded($extension), 'detail' => ''];
        }

        foreach ([storage_path(), base_path('bootstrap/cache'), dirname($this->environmentPath())] as $path) {
            $checks[] = ['key' => 'writable', 'label' => $this->relative($path), 'ok' => is_writable($path), 'detail' => ''];
        }

        return $checks;
    }

    public function ready(): bool
    {
        return collect($this->requirements())->every(fn (array $check): bool => $check['ok']);
    }

    /**
     * Connects with the details from the form and returns the connection
     * to use: "mariadb" or "mysql", from the server's own version.
     *
     * @param  array{host: string, port: int, database: string, username: string, password: string}  $db
     *
     * @throws InstallProblem
     */
    public function connect(array $db): string
    {
        try {
            $pdo = new PDO(
                "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4",
                $db['username'],
                $db['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
            $statement = $pdo->query('select version()');
            $version = $statement === false ? '' : (string) $statement->fetchColumn();
        } catch (PDOException $e) {
            throw InstallProblem::because('database', ['reason' => $this->databaseReason($e)]);
        }

        $driver = str_contains(mb_strtolower($version), 'mariadb') ? 'mariadb' : 'mysql';

        config([
            "database.connections.{$driver}" => array_merge((array) config("database.connections.{$driver}"), [
                'host' => $db['host'],
                'port' => (string) $db['port'],
                'database' => $db['database'],
                'username' => $db['username'],
                'password' => $db['password'],
            ]),
            'database.default' => $driver,
        ]);
        DB::purge($driver);

        return $driver;
    }

    /**
     * Writes the database details, a new app key (if there is none) and
     * production settings into .env, starting from .env.example when there
     * is no .env yet. Every value is read back to prove it was saved exactly.
     *
     * @param  array{host: string, port: int, database: string, username: string, password: string}  $db
     *
     * @throws InstallProblem
     */
    public function writeEnvironment(array $db, string $driver, string $url): void
    {
        $path = $this->environmentPath();
        $example = base_path('.env.example');
        $content = is_file($path) ? (string) file_get_contents($path) : (is_file($example) ? (string) file_get_contents($example) : '');
        $current = Dotenv::parse($content);

        $values = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($url, '/'),
            'APP_KEY' => filled($current['APP_KEY'] ?? null) ? (string) $current['APP_KEY'] : 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
        ];

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content) === 1
                ? (string) preg_replace_callback($pattern, fn (): string => $line, $content, 1)
                : rtrim($content).PHP_EOL.$line.PHP_EOL;
        }

        $saved = Dotenv::parse($content);

        foreach ($values as $key => $value) {
            if (($saved[$key] ?? null) !== $value) {
                throw InstallProblem::because('env_value', ['key' => $key]);
            }
        }

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw InstallProblem::because('env_write');
        }

        @chmod($path, 0600);

        // A cached config would ignore the new .env.
        @unlink(base_path('bootstrap/cache/config.php'));
        config(['app.key' => $values['APP_KEY'], 'app.url' => $values['APP_URL']]);
    }

    /**
     * Creates every table. Safe to run again: done migrations are skipped.
     *
     * @throws InstallProblem
     */
    public function migrate(): string
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            throw InstallProblem::because('migrate', ['reason' => Str::limit($e->getMessage(), 300)]);
        }

        return Artisan::output();
    }

    /** The platform super admin (the same as `php artisan platform:install`). */
    public function createAdmin(string $name, string $email, string $password): User
    {
        $user = User::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);

        $user->forceFill([
            'name' => trim($name),
            'password' => $password,
            'is_super_admin' => true,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        activity()->performedOn($user)->event('platform.installed')->log('Super admin created from /install');

        return $user;
    }

    /** Public file links (product photos); some hosts forbid symlinks, which is not fatal. */
    public function linkStorage(): bool
    {
        if (is_link(public_path('storage')) || is_dir(public_path('storage'))) {
            return true;
        }

        try {
            return Artisan::call('storage:link') === 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** Locks the installer for good and removes the setup key. */
    public function finish(): void
    {
        file_put_contents($this->lockPath(), now()->toIso8601String().PHP_EOL);
        @unlink($this->keyPath());
    }

    public function keyPath(): string
    {
        return storage_path('app/install-key.txt');
    }

    public function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    public function environmentPath(): string
    {
        return app()->environmentFilePath();
    }

    public function relative(string $path): string
    {
        return ltrim(str_replace([base_path(), '\\'], ['', '/'], $path), '/') ?: '.';
    }

    /** Single quotes keep a value literal; a value with a single quote gets escaped double quotes. */
    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/+=@-]+$/', $value) === 1) {
            return $value;
        }

        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private function databaseReason(PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        return match (true) {
            $code === 1045 => 'access_denied',
            $code === 1049 => 'unknown_database',
            $code === 2002 || $code === 2005 || str_contains($e->getMessage(), 'getaddrinfo') => 'unreachable',
            default => 'other',
        };
    }
}
