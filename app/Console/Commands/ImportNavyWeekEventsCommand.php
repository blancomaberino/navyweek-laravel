<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Import\NavyWeekEventsImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the Navy Week stops. Runs the idempotent importer
 * against the committed `database/seed-data/navy-week-events.json` artifact.
 * Safe to re-run.
 */
final class ImportNavyWeekEventsCommand extends Command
{
    protected $signature = 'import:navy-week-events';

    protected $description = 'Import the Navy Week stops (events + city detail) from database/seed-data.';

    public function handle(NavyWeekEventsImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Navy Week stops imported.');

        return self::SUCCESS;
    }
}
