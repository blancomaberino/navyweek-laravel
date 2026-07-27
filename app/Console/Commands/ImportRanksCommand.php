<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Import\RankPillarImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the ranks pillar. Runs the idempotent importer against
 * the committed `database/seed-data/ranks.json` artifact. Safe to re-run.
 */
final class ImportRanksCommand extends Command
{
    protected $signature = 'import:ranks';

    protected $description = 'Import the ranks pillar (all categories) from database/seed-data.';

    public function handle(RankPillarImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Ranks pillar imported.');

        return self::SUCCESS;
    }
}
