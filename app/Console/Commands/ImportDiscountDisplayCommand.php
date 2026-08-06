<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Fills the per-brand presentation values the discount guides render:
 *
 *  - connections.logo_display — the logo image cap ({cardMaxHeight, cardMaxWidth}),
 *    so each wordmark renders at a balanced optical size instead of one shared cap;
 *  - offers.related_slugs — the curated "More military discounts" pins;
 *  - pages.h1 — the on-page headline, which is distinct from the <title>.
 *  - pages.editorial_source_priority — the trust footer's "Source priority" line.
 *    The legacy view renders `d.sourcePriorityNote ?? {generic default}`, so a brand
 *    that carries its own note must win over the generic house string the
 *    editorial-policy backfill seeded.
 *
 * Idempotent: the connection/offer values are left alone once filled unless
 * --force. The two page columns are the exception — they always re-assert the
 * published record's own wording (which is what the live page renders) and are a
 * no-op once they match.
 */
final class ImportDiscountDisplayCommand extends Command
{
    protected $signature = 'import:discount-display {--force : Overwrite existing logo/pin values}';

    protected $description = 'Import per-brand logo sizing, related-brand pins and the guides\' page copy';

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array{logoDisplay?: array<string, array{cardMaxHeight: int, cardMaxWidth: int}>, relatedSlugs?: array<string, list<string>>, h1?: array<string, string>} $artifact */
        $artifact = SeedArtifact::read('discount-display');
        $logoBySlug = $artifact['logoDisplay'] ?? [];
        $relatedBySlug = $artifact['relatedSlugs'] ?? [];
        $h1BySlug = $artifact['h1'] ?? [];
        $force = (bool) $this->option('force');
        $logos = 0;
        $related = 0;
        $notes = 0;

        foreach ($pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            if (! $offer instanceof Offer) {
                continue;
            }

            $display = $logoBySlug[$page->slug] ?? null;
            $connection = $offer->connection;
            if ($display !== null && ($force || blank($connection->logo_display))) {
                $connection->forceFill(['logo_display' => $display])->save();
                $logos++;
            }

            $pins = $relatedBySlug[$page->slug] ?? null;
            if ($pins !== null && ($force || blank($offer->related_slugs))) {
                $offer->forceFill(['related_slugs' => $pins])->save();
                $related++;
            }

            $notes += $this->syncPageCopy($page, $offer, $h1BySlug[$page->slug] ?? null);
        }

        $this->info("Imported logo sizing for {$logos} brands, related pins for {$related} offers and page copy on {$notes} pages.");

        return self::SUCCESS;
    }

    /**
     * Re-assert the page copy the published record owns: its on-page `h1` and the
     * brand's own source-priority note. Returns 1 when the page changed.
     */
    private function syncPageCopy(Page $page, Offer $offer, ?string $h1): int
    {
        $updates = [];

        if ($h1 !== null && $h1 !== '' && $page->h1 !== $h1) {
            $updates['h1'] = $h1;
        }

        $note = $offer->source_priority_note;
        if (filled($note) && $page->editorial_source_priority !== $note) {
            $updates['editorial_source_priority'] = $note;
        }

        if ($updates === []) {
            return 0;
        }

        $page->forceFill($updates)->save();

        return 1;
    }
}
