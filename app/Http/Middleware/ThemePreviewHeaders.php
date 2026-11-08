<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Theme preview pages (?theme_preview=…) are never indexed or stored by a
 * cache: they show an unsaved draft to one person.
 */
final class ThemePreviewHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->query->has('theme_preview')) {
            $response->headers->set('X-Robots-Tag', 'noindex');
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
