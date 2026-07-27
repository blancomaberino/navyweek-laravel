<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Import\LocalDiscountImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the local (geographic) discount guides. Runs the
 * idempotent importer against the committed `database/seed-data/local-discounts.json`
 * artifact (parent discounts with nested stores + hours). Safe to re-run.
 */
final class ImportLocalDiscountsCommand extends Command
{
    protected $signature = 'import:local-discounts';

    protected $description = 'Import the local discount guides (+ stores + hours) from database/seed-data.';

    public function handle(LocalDiscountImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Local discount guides imported.');

        return self::SUCCESS;
    }
}
