<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the interface language: explicit ?lang= → session → the signed-in
 * user's preference → cookie → browser Accept-Language → app default.
 *
 * Registered globally (so error pages for unknown URLs are translated) and
 * again in the web group, where the session and cookies are available.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $query = $request->query('lang');
        $session = $request->hasSession() ? $request->session()->get('locale') : null;
        $candidates = [
            is_string($query) ? $query : null,
            is_string($session) ? $session : null,
            // The user (and the session) only exist once the web stack has started the session.
            $request->hasSession() ? $request->user()?->locale : null,
            $request->cookie(config('locales.cookie')),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && Locales::isSupported($candidate)) {
                return $candidate;
            }
        }

        $codes = Locales::codes();
        $preferred = $request->getPreferredLanguage($codes);

        return Locales::isSupported($preferred) ? (string) $preferred : (string) config('app.locale', $codes[0]);
    }
}
