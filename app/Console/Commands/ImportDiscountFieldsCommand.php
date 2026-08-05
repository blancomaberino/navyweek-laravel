<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Realigns each discount Offer's scalar copy with the published brand records.
 *
 * The platform originally imported these from the `research/` briefs, while the
 * live site renders `src/data/discounts/*.ts` — so the two drifted (a different
 * hero tagline, CTA label and headline on all 981 guides). This imports the
 * published values from a committed seed artifact.
 */
final class ImportDiscountFieldsCommand extends Command
{
    protected $signature = 'import:discount-fields {--force : Overwrite values that already differ}';

    protected $description = 'Align discount offer copy (tagline, headline, CTA labels) with the published brand records';

    /** Seed-artifact key => offers column. */
    private const MAP = [
        'heroTagline' => 'hero_tagline',
        'headlineDiscount' => 'headline_discount',
        'discountSummary' => 'discount_summary',
        'ctaLabel' => 'cta_label',
        'ctaSubnote' => 'cta_subnote',
        'stickyCtaLabel' => 'sticky_cta_label',
        'audienceLabel' => 'audience_label',
        'sourcePriorityNote' => 'source_priority_note',
    ];

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, array<string, string>> $bySlug */
        $bySlug = SeedArtifact::read('discount-fields');
        $force = (bool) $this->option('force');
        $touched = 0;

        foreach ($pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            $record = $bySlug[$page->slug] ?? null;

            if (! $offer instanceof Offer || $record === null) {
                continue;
            }

            $updates = [];
            foreach (self::MAP as $key => $column) {
                $value = $record[$key] ?? null;
                if ($value === null) {
                    continue;
                }
                if (blank($offer->getAttribute($column)) || $force) {
                    $updates[$column] = $value;
                }
            }

            if ($updates !== []) {
                $offer->forceFill($updates)->save();
                $touched++;
            }
        }

        $this->info("Aligned copy on {$touched} offers.");

        return self::SUCCESS;
    }
}
