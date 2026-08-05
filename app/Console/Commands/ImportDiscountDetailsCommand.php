<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Imports the "How it works" narrative for each discount brand onto its primary
 * Offer. Keyed by the brand's page slug, which is how the published records are
 * identified. Idempotent; only fills offers that have no details yet unless
 * --force.
 */
final class ImportDiscountDetailsCommand extends Command
{
    protected $signature = 'import:discount-details {--force : Overwrite existing details}';

    protected $description = 'Import the "How it works" paragraphs onto each discount offer';

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, list<string>> $bySlug */
        $bySlug = SeedArtifact::read('discount-details');
        $force = (bool) $this->option('force');
        $filled = 0;

        foreach ($pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            $details = $bySlug[$page->slug] ?? null;

            if (! $offer instanceof Offer || $details === null) {
                continue;
            }

            if (filled($offer->details) && ! $force) {
                continue;
            }

            $offer->forceFill(['details' => $details])->save();
            $filled++;
        }

        $this->info("Imported How-it-works details onto {$filled} offers.");

        return self::SUCCESS;
    }
}
