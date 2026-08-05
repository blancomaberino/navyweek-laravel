<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Imports the hero/trust metadata for the editorial content pages — the KeyFacts
 * card, the page-specific independence disclosure, the "// …" eyebrow, the
 * "← Navy Reference" flag and the FAQ list — into the columns and relations the
 * Blade partials already read.
 *
 * These lived only in the legacy React page views, so the ported pages rendered a
 * KeyFacts card with no rows, FAQ headings with no answers, and no disclosure at
 * all. Extracted verbatim into database/seed-data/content-page-meta.json.
 *
 * Idempotent: a field is written only when the page has no value for it, unless
 * --force. FAQs go through `replaceFaqs()`, which is delete-then-recreate.
 */
final class ImportContentPageMetaCommand extends Command
{
    protected $signature = 'import:content-page-meta {--force : Overwrite values the page already has}';

    protected $description = 'Import KeyFacts / disclosure / eyebrow / FAQs for the editorial content pages';

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, array<string, mixed>> $meta */
        $meta = SeedArtifact::read('content-page-meta');
        $force = (bool) $this->option('force');
        $imported = 0;

        foreach ($meta as $path => $row) {
            $page = $pages->findPublishedByPath($path);

            if ($page === null) {
                $this->warn("skipped {$path} — no published page");

                continue;
            }

            $changed = $this->applyColumns($page, $row, $force);
            $changed = array_merge($changed, $this->applyFaqs($page, $row, $force));

            if ($changed === []) {
                $this->line("skipped {$path} — already populated (use --force)");

                continue;
            }

            $imported++;
            $this->info("imported {$path} — ".implode(', ', $changed));
        }

        return self::SUCCESS;
    }

    /**
     * Fill the scalar/JSON columns, skipping any the page already answers for.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function applyColumns(Page $page, array $row, bool $force): array
    {
        $changed = [];
        $updates = [];

        // `false` is this flag's "unset" — the column defaults to false and only the
        // three navy-reference guides opt in, so turning it on is never a no-op write.
        if (($row['showsReferenceBacklink'] ?? false) === true && (! $page->shows_reference_backlink || $force)) {
            $updates['shows_reference_backlink'] = true;
            $changed[] = 'backlink';
        }

        foreach (['keyFacts' => 'key_facts', 'disclosure' => 'disclosure', 'eyebrow' => 'eyebrow'] as $key => $column) {
            if (! isset($row[$key])) {
                continue;
            }
            if (filled($page->getAttribute($column)) && ! $force) {
                continue;
            }
            $updates[$column] = $row[$key];
            $changed[] = $column;
        }

        if ($updates !== []) {
            $page->forceFill($updates)->save();
        }

        return $changed;
    }

    /**
     * Seed the page's FAQ rows through the shared polymorphic relation.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function applyFaqs(Page $page, array $row, bool $force): array
    {
        $faqs = $row['faqs'] ?? null;

        if (! is_array($faqs) || $faqs === []) {
            return [];
        }

        if ($page->faqs()->exists() && ! $force) {
            return [];
        }

        $rows = [];
        foreach (array_values($faqs) as $i => $faq) {
            if (! is_array($faq)) {
                continue;
            }
            $question = $faq['question'] ?? '';
            $answer = $faq['answer'] ?? '';

            $rows[] = [
                'question' => is_string($question) ? $question : '',
                'answer' => is_string($answer) ? $answer : '',
                'sort_order' => $i,
            ];
        }

        $page->replaceFaqs($rows);

        return ['faqs('.count($rows).')'];
    }
}
