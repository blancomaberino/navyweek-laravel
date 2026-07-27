<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Import\JetTeamsImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the jet-teams silo. Runs the idempotent importer against
 * the committed `database/seed-data/jet-teams.json`, `jet-team-schedule.json`, and
 * `jet-team-cities.json` artifacts. Safe to re-run.
 */
final class ImportJetTeamsCommand extends Command
{
    protected $signature = 'import:jet-teams';

    protected $description = 'Import the jet-teams silo (hubs + schedule + city guides) from database/seed-data.';

    public function handle(JetTeamsImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Jet-teams silo imported.');

        return self::SUCCESS;
    }
}
