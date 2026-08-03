<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Sitemap;

/**
 * The rendered output of one {@see SitemapGenerator} run: the files to write and the
 * bookkeeping the command reports. Pure data — the generator never touches the
 * filesystem, so it stays unit-testable and the command owns the writes.
 *
 * `files` maps a public-relative filename (`sitemap.xml`, `sitemap-events.xml`, …) to
 * its XML body, index first. `counts` is per-bucket URL counts (bucket key → n).
 * `uncovered` lists published, indexable url_paths that matched no bucket — a loud
 * signal that a new page family needs its own bucket (the legacy reconciliation net).
 */
final readonly class SitemapResult
{
    /**
     * @param  array<string, string>  $files  filename → XML body (index first)
     * @param  array<string, int>  $counts  bucket key → URL count
     * @param  list<string>  $uncovered  url_paths in no bucket
     */
    public function __construct(
        public array $files,
        public array $counts,
        public array $uncovered,
    ) {}
}
