<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateNavyWeekPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the Navy Week city pages (`/city/{slug}/`) from the imported events.
 * Run after `import:navy-week-events`. Idempotent.
 */
final class GenerateNavyWeekPagesCommand extends Command
{
    protected $signature = 'pages:generate-navy-week';

    protected $description = 'Generate the Navy Week city pages (/city/{slug}/).';

    public function handle(GenerateNavyWeekPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} Navy Week city pages.");

        return self::SUCCESS;
    }
}
