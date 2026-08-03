<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateBasePagesAction;
use Illuminate\Console\Command;

/**
 * Generates the `pages` rows for the bases pillar from the imported base records
 * (`/navy-bases/{slug}/` each). Run after `import:bases`. Idempotent — safe to
 * re-run; it upserts by url_path and preserves each page's original publish date.
 */
final class GenerateBasePagesCommand extends Command
{
    protected $signature = 'pages:generate-bases';

    protected $description = 'Generate the pages (routing/SEO rows) for every naval base.';

    public function handle(GenerateBasePagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} base pages.");

        return self::SUCCESS;
    }
}
