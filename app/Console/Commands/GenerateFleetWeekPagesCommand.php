<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateFleetWeekPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the fleet-week pages (one per city + the hub) from the imported
 * fleet-week records. Run after `import:event-guides`. Idempotent.
 */
final class GenerateFleetWeekPagesCommand extends Command
{
    protected $signature = 'pages:generate-fleet-weeks';

    protected $description = 'Generate the fleet-week city pages and the /fleetweek/ hub.';

    public function handle(GenerateFleetWeekPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} fleet-week pages.");

        return self::SUCCESS;
    }
}
