<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Import\DiscountCategoryImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the discount category hubs. Runs the idempotent importer
 * against the committed `database/seed-data/discount-categories.json` artifact.
 * Safe to re-run.
 */
final class ImportDiscountCategoriesCommand extends Command
{
    protected $signature = 'import:discount-categories';

    protected $description = 'Import the discount category hubs from database/seed-data.';

    public function handle(DiscountCategoryImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Discount category hubs imported.');

        return self::SUCCESS;
    }
}
