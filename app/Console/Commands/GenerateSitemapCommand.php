<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Sitemap\SitemapGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Regenerates the custom 9-file sitemap split + index from the live `pages` registry
 * (hand-port of `scripts/generate-sitemap.mjs`). Writes `public/sitemap.xml` +
 * `public/sitemap-*.xml`. The non-HTML `data` bucket (`/llms.txt`,
 * `/data/navy-week-2026.json`) is included only for resources that already exist in
 * `public/` (produced by `feed:generate`), each stamped with its file mtime.
 *
 * A published, indexable page whose path matches no bucket is reported as a warning
 * (and left out of the output) so a new page family gets its own bucket on purpose —
 * the reconciliation net ported from the legacy `walkDist` sweep.
 */
final class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Regenerate the sitemap index + child sitemaps from the published, indexable pages.';

    /** Non-HTML resources eligible for the `data` bucket (path is public-relative, keeps its extension). */
    private const DATA_RESOURCES = ['/llms.txt', '/data/navy-week-2026.json'];

    public function handle(SitemapGenerator $generator): int
    {
        $result = $generator->build($this->existingDataResources());

        foreach ($result->files as $name => $xml) {
            File::put(public_path($name), $xml);
        }

        $this->info('Wrote '.count($result->files).' sitemap file(s) to public/:');
        foreach ($result->counts as $bucket => $count) {
            // Only report a bucket that produced a file (always-emitted or non-empty).
            if (isset($result->files["sitemap-{$bucket}.xml"])) {
                $this->line(sprintf('  sitemap-%s.xml: %d URL(s)', $bucket, $count));
            }
        }
        $this->info('Total listed: '.array_sum($result->counts).' URL(s).');

        if ($result->uncovered !== []) {
            $this->newLine();
            $this->warn(count($result->uncovered).' indexable page(s) in NO sitemap bucket — a new family likely needs its own prefix:');
            foreach ($result->uncovered as $path) {
                $this->warn("  {$path}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * The data-bucket resources that actually exist in `public/`, each stamped with its
     * file mtime (Y-m-d) so the sitemap `lastmod` tracks the feed's freshness.
     *
     * @return list<array{path: string, lastmod: string}>
     */
    private function existingDataResources(): array
    {
        $resources = [];
        foreach (self::DATA_RESOURCES as $path) {
            $absolute = public_path(ltrim($path, '/'));
            if (! File::exists($absolute)) {
                continue;
            }
            $resources[] = [
                'path' => $path,
                'lastmod' => Carbon::createFromTimestamp(File::lastModified($absolute))->format('Y-m-d'),
            ];
        }

        return $resources;
    }
}
