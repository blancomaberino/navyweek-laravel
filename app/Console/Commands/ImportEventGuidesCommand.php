<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Import\EventGuidesImporter;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the event-guide aggregates (fleet weeks, air shows, and
 * the air-show hub). Runs the idempotent importer against the committed
 * `database/seed-data` artifacts. Safe to re-run.
 */
final class ImportEventGuidesCommand extends Command
{
    protected $signature = 'import:event-guides';

    protected $description = 'Import the fleet-week + air-show guides (and hub) from database/seed-data.';

    public function handle(EventGuidesImporter $importer): int
    {
        $counts = $importer->import();

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Event guides imported.');

        return self::SUCCESS;
    }
}
