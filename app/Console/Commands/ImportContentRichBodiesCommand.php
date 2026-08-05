<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Replaces `pages.body_blocks` on the long-form editorial pages with the RICH
 * block list — the same copy, but carrying the structure the first import had no
 * block type for: rate/comparison tables, gold-ruled callouts, the jump navs, the
 * FAQ answers, the contact cards, and the inline emphasis/source links inside
 * every paragraph.
 *
 * The artifact (`database/seed-data/content-rich-bodies.json`) is keyed by
 * `pages.url_path` and holds a complete, ordered body per page, so the command is
 * idempotent: running it again writes the same blocks. It is a one-way seed —
 * editor changes made in Filament are overwritten, which is why it only runs with
 * `--force` once a page's body already matches the artifact.
 */
final class ImportContentRichBodiesCommand extends Command
{
    protected $signature = 'import:content-rich-bodies {--force : Overwrite a body that has diverged from the artifact}';

    protected $description = 'Import the rich (tables/callouts/links) long-form page bodies into pages.body_blocks';

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, array{eyebrow?: string, h1?: string, blocks: list<array<string, mixed>>}> $bodies */
        $bodies = SeedArtifact::read('content-rich-bodies');
        $force = (bool) $this->option('force');
        $imported = 0;

        foreach ($bodies as $path => $entry) {
            $page = $pages->findPublishedByPath($path);

            if ($page === null) {
                $this->warn("skipped {$path} — no published page");

                continue;
            }

            $blocks = $entry['blocks'];

            // Hero furniture the policy pages never got: the legacy kicker and the
            // literal (upper-case) h1. Only ever fills a blank — an editor's value wins.
            $hero = array_filter([
                'eyebrow' => $entry['eyebrow'] ?? null,
                'h1' => $entry['h1'] ?? null,
            ], static fn (?string $value, string $key): bool => $value !== null && blank($page->{$key}), ARRAY_FILTER_USE_BOTH);

            if ($hero !== []) {
                $page->forceFill($hero)->save();
            }

            /** @var list<array<string, mixed>> $current */
            $current = array_values($page->body_blocks ?? []);

            if ($current === $blocks) {
                $this->line("unchanged {$path}");

                continue;
            }

            // A body that is neither the artifact nor the plain-text first import
            // has been edited in the CMS; don't clobber it without --force.
            if ($current !== [] && ! $force && ! $this->isPlainImport($current)) {
                $this->warn("skipped {$path} — body was edited (use --force)");

                continue;
            }

            $page->forceFill(['body_blocks' => $blocks])->save();
            $imported++;
            $this->info("imported {$path} — ".count($blocks).' blocks');
        }

        return self::SUCCESS;
    }

    /**
     * True when every block is one the plain-text `import:content-bodies` pass
     * emits — i.e. the body has never been enriched or hand-edited.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function isPlainImport(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (! in_array($block['type'] ?? '', ['heading', 'paragraph', 'list_item', 'note'], true)) {
                return false;
            }

            if (array_key_exists('spans', $block)) {
                return false;
            }
        }

        return true;
    }
}
