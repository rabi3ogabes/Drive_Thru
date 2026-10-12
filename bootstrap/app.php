<?php

use App\Http\Middleware\NormalizeDigits;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetLocale::class);

        // Payment providers post back without our CSRF token; their signatures are checked instead.
        $middleware->validateCsrfTokens(except: ['payments/sadad/callback', 'payments/sadad/webhook', 'webhooks/*']);

        // Pages outside the panels (ops screens, invoice PDFs) send guests to the restaurant sign-in.
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));

        $middleware->web(append: [
            SetLocale::class,
            NormalizeDigits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The public API never reveals internal class names in a 404.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['error' => ['code' => 'not_found', 'message' => 'Not found.']], 404)
            : null);
    })->create();
