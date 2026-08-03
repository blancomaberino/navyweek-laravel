<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Sitemap;

use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Seo\SeoUrl;

/**
 * Hand-port of the legacy `scripts/generate-sitemap.mjs` — the custom 9-file bucket
 * split + index (NOT a generic sitemap package; parity is load-bearing for search).
 *
 * The platform adaptation: the `pages` table is the single route registry, so this
 * reads published + indexable pages (via the repository) instead of re-deriving routes
 * from the TypeScript data registries and scraping built HTML for `noindex`/canonical.
 * `lastmod` comes from each page's build-clock `date_modified` — which already encodes
 * the legacy "only bump when the record changed" rule — rather than seeding from the
 * prior sitemap XML.
 *
 * Buckets are assigned purely by canonical `url_path` prefix (mirroring how the legacy
 * script constructed each bucket's paths), so this is decoupled from `PageType` and a
 * new family buckets automatically once its prefix is listed. A published, indexable
 * page whose path matches no bucket is excluded from the output and surfaced in
 * {@see SitemapResult::$uncovered} — the reconciliation safety-net that turns a silent
 * omission into a loud warning (as the legacy `walkDist` sweep did).
 *
 * Pure: it returns the file bodies to write ({@see SitemapResult}); the command owns
 * the filesystem so this stays unit-testable.
 */
final class SitemapGenerator
{
    /** Fallback lastmod for a page with no build-clock date (mirrors the legacy SEED_FALLBACK_DATE). */
    private const FALLBACK_DATE = '2026-06-21';

    /**
     * Buckets in the legacy file order. `events`, `guides`, and `reference` are always
     * emitted (as the legacy did); the rest are emitted only when non-empty.
     *
     * @var list<string>
     */
    private const ALWAYS_EMITTED = ['events', 'guides', 'reference'];

    /**
     * Jet-team hub/city paths share no common prefix (`/blue-angels/`, `/thunderbirds/`
     * — the TeamId slugs), so their prefixes are listed explicitly. A new team not
     * listed here trips the uncovered warning rather than silently vanishing.
     *
     * @var list<string>
     */
    private const JET_TEAM_PREFIXES = ['/blue-angels/', '/thunderbirds/'];

    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * Build the sitemap index + child sitemaps from the live route registry.
     *
     * @param  list<array{path: string, lastmod: string}>  $dataResources  non-HTML LLM/feed
     *                                                                     resources for the `data` bucket (e.g. `/llms.txt`, `/data/navy-week-2026.json`);
     *                                                                     the caller supplies only those that exist. Paths keep their file extension
     *                                                                     (no trailing slash).
     */
    public function build(array $dataResources = []): SitemapResult
    {
        /** @var array<string, list<array{loc: string, lastmod: string}>> $buckets */
        $buckets = [];
        $uncovered = [];

        foreach ($this->pages->allPublishedIndexable() as $page) {
            $bucket = $this->bucketFor($page->url_path);
            if ($bucket === null) {
                $uncovered[] = $page->url_path;

                continue;
            }
            $buckets[$bucket][] = [
                'loc' => SeoUrl::absolute($page->url_path),
                'lastmod' => $this->lastmodFor($page),
            ];
        }

        if ($dataResources !== []) {
            $buckets['data'] = array_map(static fn (array $r): array => [
                // Data resources keep their extension — no trailing slash.
                'loc' => SeoUrl::site().$r['path'],
                'lastmod' => $r['lastmod'],
            ], $dataResources);
        }

        return $this->render($buckets, $uncovered);
    }

    /**
     * Assign a canonical `url_path` to its bucket, most-specific first. Returns null
     * when no bucket matches (an uncovered page).
     */
    private function bucketFor(string $path): ?string
    {
        return match (true) {
            $path === '/', $path === '/schedule/', $path === '/map/', str_starts_with($path, '/city/') => 'events',
            $path === '/navy-reference/',
            $path === '/our-process/',
            $path === '/va-disability/',
            $path === '/veterans-day/',
            $path === '/veterans-day/free-meals/',
            $path === '/veterans-home-care/',
            $path === '/best-credit-cards-for-military/',
            str_starts_with($path, '/authors/') => 'guides',
            str_starts_with($path, '/navy-bases'),
            $path === '/navy-ranks/',
            $path === '/navy-ratings/',
            str_starts_with($path, '/navy-designators') => 'reference',
            // Local geo tree (`/discounts/…`) is checked before the national directory
            // (`/discount/…`); the trailing slash keeps the two prefixes disjoint.
            str_starts_with($path, '/discounts/') => 'local-discounts',
            $path === '/discount/', str_starts_with($path, '/discount/') => 'discounts',
            str_starts_with($path, '/fleetweek/') => 'fleetweek',
            str_starts_with($path, '/air-show/') => 'air-show',
            $this->isJetTeamPath($path) => 'jetteams',
            default => null,
        };
    }

    private function isJetTeamPath(string $path): bool
    {
        foreach (self::JET_TEAM_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Build-clock `date_modified` (Y-m-d), falling back to `date_published`, then the fixed date. */
    private function lastmodFor(Page $page): string
    {
        return ($page->date_modified ?? $page->date_published)?->format('Y-m-d') ?? self::FALLBACK_DATE;
    }

    /**
     * @param  array<string, list<array{loc: string, lastmod: string}>>  $buckets
     * @param  list<string>  $uncovered
     */
    private function render(array $buckets, array $uncovered): SitemapResult
    {
        $files = [];
        $counts = [];
        /** @var list<array{file: string, lastmod: string}> $children */
        $children = [];

        foreach ($this->bucketOrder() as $bucket) {
            $entries = $buckets[$bucket] ?? [];
            $counts[$bucket] = count($entries);

            // events/guides/reference always ship (parity); the rest only when non-empty.
            if ($entries === [] && ! in_array($bucket, self::ALWAYS_EMITTED, true)) {
                continue;
            }

            $file = "sitemap-{$bucket}.xml";
            $files[$file] = $this->renderUrlset($entries);
            $children[] = ['file' => $file, 'lastmod' => $this->maxLastmod($entries)];
        }

        // Index first in the file list.
        $files = ['sitemap.xml' => $this->renderIndex($children)] + $files;

        return new SitemapResult($files, $counts, $uncovered);
    }

    /** @return list<string> */
    private function bucketOrder(): array
    {
        return ['events', 'guides', 'reference', 'discounts', 'local-discounts', 'fleetweek', 'jetteams', 'air-show', 'data'];
    }

    /**
     * @param  list<array{loc: string, lastmod: string}>  $entries
     */
    private function renderUrlset(array $entries): string
    {
        $urls = array_map(static fn (array $e): string => "  <url>\n"
            ."    <loc>{$e['loc']}</loc>\n"
            ."    <lastmod>{$e['lastmod']}</lastmod>\n"
            .'  </url>', $entries);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .($urls === [] ? '' : implode("\n", $urls)."\n")
            ."</urlset>\n";
    }

    /**
     * @param  list<array{file: string, lastmod: string}>  $children
     */
    private function renderIndex(array $children): string
    {
        $site = SeoUrl::site();
        $entries = array_map(static fn (array $c): string => "  <sitemap>\n"
            ."    <loc>{$site}/{$c['file']}</loc>\n"
            ."    <lastmod>{$c['lastmod']}</lastmod>\n"
            .'  </sitemap>', $children);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .($entries === [] ? '' : implode("\n", $entries)."\n")
            ."</sitemapindex>\n";
    }

    /**
     * The freshest entry date (the legacy `maxLastmod`), or the fallback for an empty bucket.
     *
     * @param  list<array{loc: string, lastmod: string}>  $entries
     */
    private function maxLastmod(array $entries): string
    {
        $max = '';
        foreach ($entries as $entry) {
            if ($entry['lastmod'] > $max) {
                $max = $entry['lastmod'];
            }
        }

        return $max === '' ? self::FALLBACK_DATE : $max;
    }
}
