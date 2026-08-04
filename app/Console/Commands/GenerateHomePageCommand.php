<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateHomePageAction;
use Illuminate\Console\Command;

/**
 * Seeds the home landing page (`/`) — the `pages` row + the home FAQs. The body renders
 * the live Navy Week schedule from the pillar, so nothing else is stored. Idempotent —
 * never clobbers an editor's FAQ edits on re-run.
 */
final class GenerateHomePageCommand extends Command
{
    protected $signature = 'pages:generate-home';

    protected $description = 'Seed the home landing page (/) — the pages row + home FAQs.';

    public function handle(GenerateHomePageAction $action): int
    {
        $action();

        $this->info('Seeded the home landing page (/).');

        return self::SUCCESS;
    }
}
