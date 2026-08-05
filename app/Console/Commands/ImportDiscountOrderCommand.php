<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Imports each brand's position in the published registry onto its Offer's
 * `sort_order`.
 *
 * The Deals mega-menu and the Deals section sort by publish date descending, and
 * the live site breaks ties with the curated registry order. Without it we fell
 * back to alphabetical `url_path`, so from the first tie onward every one of the
 * 982 cards showed a different brand than the live page — a large pixel diff on
 * EVERY page, despite identical layout.
 */
final class ImportDiscountOrderCommand extends Command
{
    protected $signature = 'import:discount-order';

    protected $description = 'Import the published registry order onto discount offers';

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, int> $bySlug */
        $bySlug = SeedArtifact::read('discount-order');
        $touched = 0;

        foreach ($pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            $position = $bySlug[$page->slug] ?? null;

            if (! $offer instanceof Offer || $position === null) {
                continue;
            }

            if ($offer->sort_order !== $position) {
                $offer->forceFill(['sort_order' => $position])->save();
                $touched++;
            }
        }

        $this->info("Set registry order on {$touched} offers.");

        return self::SUCCESS;
    }
}
