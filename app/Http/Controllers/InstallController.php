<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Install\Installer;
use App\Support\Install\InstallProblem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

/**
 * /install: the first install from the browser. It runs outside the web
 * middleware (no session, cookies or CSRF) because before it the app has
 * no key and no tables; the setup key from the server's files protects
 * every submit instead. Once installed it answers 404 for good.
 */
final class InstallController
{
    public function __construct(private readonly Installer $installer) {}

    public function show(): View|Response
    {
        if ($this->installer->isInstalled()) {
            abort(404);
        }

        $this->installer->setupKey();

        return $this->page();
    }

    public function run(Request $request): View|Response
    {
        if ($this->installer->isInstalled()) {
            abort(404);
        }

        // 32 random characters: guessing is hopeless, so no rate limit (it would need the cache tables).
        if (! $this->installer->keyMatches((string) $request->input('key'))) {
            return $this->page(['key' => __('install.errors.key')], $request, 422);
        }

        $validator = Validator::make($request->all(), [
            'db_host' => ['required', 'string', 'max:190'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_$-]+$/'],
            'db_username' => ['required', 'string', 'max:80'],
            'db_password' => ['nullable', 'string', 'max:190'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ], [], __('install.attributes'));

        if ($validator->fails()) {
            return $this->page($validator->errors()->toArray(), $request, 422);
        }

        $db = [
            'host' => trim((string) $request->input('db_host')),
            'port' => (int) $request->input('db_port'),
            'database' => trim((string) $request->input('db_database')),
            'username' => trim((string) $request->input('db_username')),
            'password' => (string) $request->input('db_password', ''),
        ];

        try {
            $driver = $this->installer->connect($db);
            $this->installer->writeEnvironment($db, $driver, $request->getSchemeAndHttpHost());
            $this->installer->migrate();
            $admin = $this->installer->createAdmin((string) $request->input('name'), (string) $request->input('email'), (string) $request->input('password'));
            $linked = $this->installer->linkStorage();
            $this->installer->finish();
        } catch (InstallProblem $problem) {
            return $this->page(['install' => $problem->getMessage()], $request, 422);
        }

        return view('install.done', [
            'email' => $admin->email,
            'adminUrl' => rtrim($request->getSchemeAndHttpHost(), '/').'/admin',
            'linked' => $linked,
        ]);
    }

    /**
     * @param  array<string, string|array<string>>  $errors  one message, or a validator's list per field
     */
    private function page(array $errors = [], ?Request $request = null, int $status = 200): Response|View
    {
        $view = view('install.form', [
            'requirements' => $this->installer->requirements(),
            'ready' => $this->installer->ready(),
            'keyFile' => $this->installer->relative($this->installer->keyPath()),
            'problems' => collect($errors)->map(fn (string|array $messages): string => is_array($messages) ? (string) ($messages[0] ?? '') : $messages)->all(),
            'old' => $request?->except(['key', 'db_password', 'password', 'password_confirmation']) ?? [],
        ]);

        return $status === 200 ? $view : response($view, $status);
    }
}
