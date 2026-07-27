<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Import\BasePillarImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the bases pillar. Runs the idempotent importer against
 * the committed `database/seed-data` artifacts (states → countries → bases, in
 * FK order). Safe to re-run: it upserts by slug.
 */
final class ImportBasesCommand extends Command
{
    protected $signature = 'import:bases';

    protected $description = 'Import the bases pillar (+ us_states/overseas_countries lookups) from database/seed-data.';

    public function handle(BasePillarImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Bases pillar imported.');

        return self::SUCCESS;
    }
}
