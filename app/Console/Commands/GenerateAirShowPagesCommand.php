<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateAirShowPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the air-show pages (published detail pages + the hub) from the imported
 * air-show records. Run after `import:event-guides`. Idempotent.
 */
final class GenerateAirShowPagesCommand extends Command
{
    protected $signature = 'pages:generate-air-shows';

    protected $description = 'Generate the air-show detail pages and the /air-show/ hub.';

    public function handle(GenerateAirShowPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} air-show pages.");

        return self::SUCCESS;
    }
}
