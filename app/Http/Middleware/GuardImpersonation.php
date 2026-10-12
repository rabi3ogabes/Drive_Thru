<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * While viewing a workspace as its owner, the owner's personal security
 * settings (profile, password, two-factor, devices) stay off limits.
 */
final class GuardImpersonation
{
    /** @var list<string> Route-name patterns blocked while impersonating. */
    private const BLOCKED = [
        'filament.*.auth.profile',
        'filament.*.pages.account.sessions',
        'filament.*.auth.multi-factor-authentication.*',
    ];

    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->isActive() && Str::is(self::BLOCKED, (string) $request->route()?->getName())) {
            abort(403, __('clients.impersonation.blocked'));
        }

        return $next($request);
    }
}
