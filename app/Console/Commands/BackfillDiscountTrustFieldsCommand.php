<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Backfills the CMS trust columns on every published discount-brand page from
 * data already held on its Offer/Connection, so the shared trust chrome (byline
 * dates + editorial policy + corrections) renders on the discount guides.
 *
 * Idempotent and non-destructive: an editor-supplied value already in a column is
 * left alone — this only fills what is still NULL. Re-runnable after an import.
 */
final class BackfillDiscountTrustFieldsCommand extends Command
{
    protected $signature = 'backfill:discount-trust {--force : Overwrite existing values}';

    protected $description = 'Fill the trust CMS columns (h1, review dates, editorial policy) on discount-brand pages';

    private const SOURCE_PRIORITY = "We cite the brand's own official discount page and its verification partner (ID.me, GovX, SheerID) first. Offers are confirmed against the brand's published terms before publication; third-party coupon aggregators are never used as primary evidence.";

    private const REVIEW_CADENCE = 'Discount terms, verification method, and exclusions are re-verified on a rolling cadence and at every page update.';

    public function handle(PageRepositoryInterface $pages): int
    {
        $force = (bool) $this->option('force');
        $filled = 0;

        foreach ($pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            if (! $offer instanceof Offer) {
                continue;
            }

            $brand = $offer->connection->brand;
            $updates = array_filter([
                'h1' => $this->valueFor($page, 'h1', $force, "{$brand} Military & Veteran Discount"),
                'trust_page_label' => $this->valueFor($page, 'trust_page_label', $force, "{$brand} military discount guide"),
                'editorial_source_priority' => $this->valueFor($page, 'editorial_source_priority', $force, self::SOURCE_PRIORITY),
                'editorial_review_cadence' => $this->valueFor($page, 'editorial_review_cadence', $force, self::REVIEW_CADENCE),
                // The brief's verification date is the closest thing to "last reviewed".
                'last_reviewed' => $this->valueFor($page, 'last_reviewed', $force, $offer->connection->last_verified_at?->toDateString() ?? $page->date_modified?->toDateString()),
            ], static fn ($v): bool => $v !== null);

            if ($updates !== []) {
                $page->forceFill($updates)->save();
                $filled++;
            }
        }

        $this->info("Backfilled trust fields on {$filled} discount pages.");

        return self::SUCCESS;
    }

    /** The new value for a column, or null to leave it untouched. */
    private function valueFor(Page $page, string $column, bool $force, ?string $default): ?string
    {
        if ($default === null) {
            return null;
        }

        return ($force || blank($page->getAttribute($column))) ? $default : null;
    }
}
