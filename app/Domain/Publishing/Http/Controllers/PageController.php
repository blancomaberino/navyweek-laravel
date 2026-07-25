<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Publishing\Models\Page;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single catch-all page renderer, keyed on `pages.url_path`. By the time a request
 * reaches here CanonicalUrlMiddleware has already resolved every redirect, so an
 * unknown path is a genuine 404 (the middleware sends stray legacy URLs to "/").
 *
 * This slice returns a minimal 200 to prove routing end-to-end; Phase 3 swaps the
 * body for the Blade + JSON-LD render behind the response cache.
 */
final class PageController
{
    public function show(Request $request): Response
    {
        // Look up by the exact path the middleware already canonicalized and
        // validated — re-normalizing here (lowercasing/slash-collapsing) would use
        // a different key than the middleware's existence check and could serve a
        // page for a non-canonical URL the middleware meant to 301.
        $page = Page::query()
            ->where('is_published', true)
            ->where('url_path', $request->getPathInfo())
            ->first();

        if ($page === null) {
            abort(404);
        }

        // Placeholder until Phase 3 renders real page bodies.
        return response("OK: {$page->url_path}")
            ->header('Content-Type', 'text/plain');
    }
}
