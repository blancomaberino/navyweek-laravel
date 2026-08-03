<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateRankPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the two consolidated reference-list pages (`/navy-ranks/`,
 * `/navy-ratings/`) from the imported rank records. Run after `import:ranks`.
 * Idempotent — safe to re-run.
 */
final class GenerateRankPagesCommand extends Command
{
    protected $signature = 'pages:generate-ranks';

    protected $description = 'Generate the /navy-ranks/ and /navy-ratings/ list pages.';

    public function handle(GenerateRankPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} rank list pages.");

        return self::SUCCESS;
    }
}
