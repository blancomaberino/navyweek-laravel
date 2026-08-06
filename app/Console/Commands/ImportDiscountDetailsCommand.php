<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Console\Command;

/**
 * Imports a prose column (`details` = "How it works", `intro` = the lead
 * paragraphs) onto each discount brand's primary Offer from a committed seed
 * artifact, keyed by the brand's page slug. Idempotent; only fills offers whose
 * column is still empty unless --force.
 */
final class ImportDiscountDetailsCommand extends Command
{
    protected $signature = 'import:discount-prose {artifact=discount-details} {column=details} {--force : Overwrite existing values}';

    protected $description = 'Import a discount prose column (details / intro) onto each offer from a seed artifact';

    public function handle(PageRepositoryInterface $pages): int
    {
        /** @var array<string, list<string>> $bySlug */
        $bySlug = SeedArtifact::read((string) $this->argument('artifact'));
        $column = (string) $this->argument('column');
        $force = (bool) $this->option('force');
        $filled = 0;

        foreach ($pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            $value = $bySlug[$page->slug] ?? null;

            if (! $offer instanceof Offer || $value === null) {
                continue;
            }

            if (filled($offer->getAttribute($column)) && ! $force) {
                continue;
            }

            $offer->forceFill([$column => $value])->save();
            $filled++;
        }

        $this->info("Imported {$column} onto {$filled} offers.");

        return self::SUCCESS;
    }
}
