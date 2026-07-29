<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\SeoHead;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single catch-all page renderer, keyed on `pages.url_path`. By the time a request
 * reaches here CanonicalUrlMiddleware has already resolved every redirect, so an
 * unknown path is a genuine 404 (the middleware sends stray legacy URLs to "/").
 *
 * The base layout ports the legacy `<head>` furniture 1:1 and `SeoHead` serializes
 * the per-page SEO block; the page body is a minimal shell until the per-page-type
 * views land (one page-family per follow-up PR). Response caching is a later slice.
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

        $seo = SeoHead::forPage($page);

        return response()->view('pages.show', [
            'page' => $page,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }
}
