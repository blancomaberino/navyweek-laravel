<?php

use App\Domain\Publishing\Http\Middleware\CanonicalUrlMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Bound the Host header (reflected into redirect Location by
        // CanonicalUrlMiddleware) to our own domains so a spoofed Host can't turn a
        // 301 into an open redirect. Enforced outside local/testing; the app-URL
        // host is always trusted, so dev + the test suite are unaffected.
        $middleware->trustHosts(at: ['navyweek.org', 'www.navyweek.org']);

        // GLOBAL (not web-group) and first, matching the legacy Vercel edge, which
        // runs on every request regardless of method or whether a route matches —
        // e.g. apex→www must 301 a POST too, and web-group middleware only runs for
        // matched routes. Also lands before the (Phase 6) response cache so 301s are
        // never cached as page bodies.
        $middleware->prepend(CanonicalUrlMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
