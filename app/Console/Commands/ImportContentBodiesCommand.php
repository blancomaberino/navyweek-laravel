<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Imports the long-form bodies for the YMYL guides + the credit-cards guide into
 * `pages.body_blocks`, so the CMS owns content that previously existed only on
 * the published site (these pages rendered with almost none of their sections).
 *
 * Blocks that duplicate chrome the Blade trust partials already render — the
 * independence disclosure, the byline lines, and the fixed "Editorial policy"
 * bullets — are dropped so the page doesn't show them twice.
 */
final class ImportContentBodiesCommand extends Command
{
    protected $signature = 'import:content-bodies {--force : Overwrite an existing body}';

    protected $description = 'Import the long-form page bodies into pages.body_blocks';

    /** Headings after which everything is trust chrome the partials already render. */
    private const STOP_HEADINGS = ['editorial policy'];

    /** Leading fragments that belong to the disclosure/byline partials, not the body. */
    private const CHROME_PREFIXES = [
        'navyweek.org is an independent publication',
        'navyweek.org is an independent editorial publisher',
        // NOT 'written by' / 'reviewed by': those match no byline row in any
        // artifact (the byline is rendered from CMS columns, never imported as a
        // block), but they DO match real body prose — "Reviewed by a Veterans Law
        // Judge at the Board of Veterans' Appeals…" was being silently dropped
        // from /va-disability/'s claim-types section. A prefix that only ever
        // fires on a false positive is a bug, not a filter.
        'last reviewed:',
        'sources checked:',
        'how we research & review',
        'see something out of date',
        'report an outdated fact',
        'source priority.',
        'independence.',
        'review cadence.',
        'reviewer.',
        'corrections.',
        'not advice.',
    ];

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, list<array<string, mixed>>> $bodies */
        $bodies = SeedArtifact::read('content-bodies');
        $force = (bool) $this->option('force');
        $imported = 0;

        foreach ($bodies as $path => $blocks) {
            $page = $pages->findPublishedByPath($path);

            if ($page === null) {
                $this->warn("skipped {$path} — no published page");

                continue;
            }

            if (filled($page->body_blocks) && ! $force) {
                $this->line("skipped {$path} — already has a body (use --force)");

                continue;
            }

            $clean = $this->stripChrome($blocks);
            $page->forceFill(['body_blocks' => $clean])->save();
            $imported++;
            $this->info("imported {$path} — ".count($clean).' blocks');
        }

        return $imported > 0 ? self::SUCCESS : self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    private function stripChrome(array $blocks): array
    {
        $out = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $raw = $block['text'] ?? '';
            $text = mb_strtolower(is_string($raw) ? $raw : '');

            if (($block['type'] ?? '') === 'heading' && in_array($text, self::STOP_HEADINGS, true)) {
                break; // everything below is the editorial-policy partial
            }

            foreach (self::CHROME_PREFIXES as $prefix) {
                if (str_starts_with($text, $prefix)) {
                    continue 2;
                }
            }

            /** @var array<string, mixed> $block */
            $out[] = $block;
        }

        return $out;
    }
}
