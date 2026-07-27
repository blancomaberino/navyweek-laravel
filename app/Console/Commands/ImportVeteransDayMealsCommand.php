<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Import\VeteransDayMealImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the Veterans Day meal roundup. Runs the idempotent
 * importer against the committed `database/seed-data/veterans-day-meals.json`
 * artifact. Safe to re-run.
 */
final class ImportVeteransDayMealsCommand extends Command
{
    protected $signature = 'import:veterans-day-meals';

    protected $description = 'Import the Veterans Day free-meal roundup from database/seed-data.';

    public function handle(VeteransDayMealImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Veterans Day meals imported.');

        return self::SUCCESS;
    }
}
