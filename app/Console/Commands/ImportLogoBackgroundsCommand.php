<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Imports each brand's logo chip background colour onto its Connection, keyed by
 * the brand's page slug. Dark brand logos need a light chip and vice versa, so a
 * single hardcoded white chip renders many logos invisible.
 */
final class ImportLogoBackgroundsCommand extends Command
{
    protected $signature = 'import:logo-backgrounds {--force : Overwrite existing values}';

    protected $description = 'Import the per-brand logo chip background colour';

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, string> $bySlug */
        $bySlug = SeedArtifact::read('discount-logo-backgrounds');
        $force = (bool) $this->option('force');
        $filled = 0;

        foreach ($pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            $background = $bySlug[$page->slug] ?? null;

            if (! $offer instanceof Offer || $background === null) {
                continue;
            }

            $connection = $offer->connection;
            if (filled($connection->logo_background) && ! $force) {
                continue;
            }

            $connection->forceFill(['logo_background' => $background])->save();
            $filled++;
        }

        $this->info("Imported logo backgrounds onto {$filled} brands.");

        return self::SUCCESS;
    }
}
