<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Middleware;

use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Repositories\RedirectRepositoryInterface;
use App\Domain\Publishing\Services\LegacyPathResolver;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ports the legacy Vercel edge `middleware.ts` order 1:1 (PRD §5):
 *
 *   1. apex → www 301
 *   2. method gate (only GET/HEAD redirect)
 *   3. extension gate (.htm(l) continues to the resolver; other assets pass)
 *   4. trailing-slash 301
 *   5a. algorithmic /discount/category/<slug>/ → /discount/<slug>-military-veteran/
 *   5b. redirects table (exact, then longest prefix) — the hand-coded 301s + the
 *       /navy-ranks|/navy-ratings collapses, now DB rows
 *   6. MODERN_ROUTE_PREFIXES gate → LegacyPathResolver fuzzy city resolve
 *   7. catch-all: a live route continues, anything else 301 → "/"
 *
 * Registered before the response cache so 301s are never cached as page bodies.
 */
final class CanonicalUrlMiddleware
{
    public function __construct(
        private readonly RedirectRepositoryInterface $redirects,
        private readonly PageRepositoryInterface $pages,
        private readonly LegacyPathResolver $resolver,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->getSchemeAndHttpHost();
        $pathname = $request->getPathInfo();

        // The Filament admin panel (path 'admin' in AdminPanelProvider) is not a
        // legacy public route: it owns its own routing, auth, and redirects. The
        // canonical/redirect pipeline must pass it straight through — otherwise the
        // catch-all (step 7) 301s every /admin/** path to "/" and the panel is
        // unreachable.
        if ($pathname === '/admin' || str_starts_with($pathname, '/admin/')) {
            return $next($request);
        }
        // Use the RAW query string, not getQueryString(): Symfony's normalize step
        // ksort()s and RFC3986-re-encodes params, which would reorder/rewrite UTM
        // and other query bytes on every 301. The legacy edge preserves them verbatim.
        $query = $request->server->get('QUERY_STRING');
        $search = is_string($query) && $query !== '' ? '?'.$query : '';

        // 1. apex -> www (belt-and-suspenders; primary mechanism is the edge/DNS config).
        if (mb_strtolower($request->getHost()) === mb_strtolower(Config::string('site.apex_host'))) {
            return $this->redirect('https://'.Config::string('site.canonical_host').$pathname.$search);
        }

        $method = $request->getMethod();
        if ($method !== 'GET' && $method !== 'HEAD') {
            return $next($request);
        }

        $lastSlash = strrpos($pathname, '/');
        $lastSeg = $lastSlash === false ? $pathname : substr($pathname, $lastSlash + 1);
        $hasExt = (bool) preg_match('/\.[a-zA-Z0-9]+$/', $lastSeg);
        $isHtml = (bool) preg_match('/\.html?$/i', $lastSeg);

        // Legacy URLs are .htm(l) files and must reach the resolver; every other
        // extension (favicons, /og/*.png, robots.txt, /data/*.json) is a real asset.
        if ($hasExt && ! $isHtml) {
            return $next($request);
        }

        // 2. trailing slash (never for the root or file paths).
        if ($pathname !== '/' && ! str_ends_with($pathname, '/') && ! $hasExt) {
            return $this->redirect($origin.$pathname.'/'.$search);
        }

        $decoded = rawurldecode($pathname);
        $lower = mb_strtolower($decoded, 'UTF-8');

        // 5a. Retired category-hub shape (algorithmic): /discount/category/<slug>/
        //     → /discount/<slug>-military-veteran/.
        if (str_starts_with($lower, '/discount/category/')) {
            $parts = array_values(array_filter(explode('/', rtrim($decoded, '/')), static fn (string $s): bool => $s !== ''));
            // array_filter dropped empty segments, so a present [2] is non-empty.
            $slug = $parts[2] ?? null;
            if ($slug !== null) {
                $newSlug = str_ends_with($slug, '-military-veteran') ? $slug : $slug.'-military-veteran';

                return $this->redirect($origin.'/discount/'.$newSlug.'/'.$search);
            }
        }

        // 5b. Redirect store: every other hand-coded 301 + the ranks/ratings prefix
        //     collapses. The path is already trailing-slash-normalized by step 2.
        $match = $this->redirects->matchFor($lower);
        if ($match !== null) {
            $this->redirects->incrementHits($match);

            return $this->redirect($origin.$match->to_path.$search, $match->status);
        }

        // 6. Legacy gate + fuzzy resolve (skipped for live route prefixes).
        $isModern = false;
        $prefixes = array_filter(Config::array('site.modern_route_prefixes'), is_string(...));
        foreach ($prefixes as $prefix) {
            if ($lower === $prefix || str_starts_with($lower, $prefix)) {
                $isModern = true;
                break;
            }
        }

        if (! $isModern && ! str_contains($decoded, '..') && ! str_contains($decoded, "\0")) {
            $target = $this->resolver->resolve($pathname);
            if ($target !== null && $target !== $decoded) {
                return $this->redirect($origin.$this->withTrailingSlash($target).$search)
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        }

        // 7. Catch-all: the root and live routes continue; everything else 301 → "/".
        //    Route existence is case-sensitive (url_paths are canonical lowercase),
        //    matching the legacy build-time route manifest. The explicit lowercase
        //    guard reproduces that case-sensitivity on any DB collation (a MySQL
        //    _ci column would otherwise match /Discount/Nike/ and serve a duplicate).
        $isCanonicalCase = $pathname === mb_strtolower($pathname, 'UTF-8');
        if ($pathname === '/' || ($isCanonicalCase && $this->pages->publishedPathExists($pathname))) {
            return $next($request);
        }

        // Note: the legacy catch-all drops the query string on the final "/".
        return $this->redirect($origin.'/');
    }

    private function redirect(string $url, int $status = 301): RedirectResponse
    {
        return redirect()->away($url, $status);
    }

    /**
     * Port of middleware.ts withTrailingSlash: append "/" unless the path is the
     * root, already slashed, or ends in a file extension.
     */
    private function withTrailingSlash(string $p): string
    {
        if ($p === '' || $p === '/') {
            return '/';
        }
        if (str_ends_with($p, '/')) {
            return $p;
        }
        $seg = substr($p, (int) strrpos($p, '/') + 1);
        if (preg_match('/\.[a-zA-Z0-9]+$/', $seg)) {
            return $p;
        }

        return $p.'/';
    }
}
