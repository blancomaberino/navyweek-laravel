<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateVeteransDayPageAction;
use Illuminate\Console\Command;

/**
 * Seeds the `/veterans-day/` content page (body + 9 FAQs, migrated from the legacy page).
 * Idempotent — never clobbers an editor's body/FAQs on re-run.
 */
final class GenerateVeteransDayPageCommand extends Command
{
    protected $signature = 'pages:generate-veterans-day';

    protected $description = 'Seed the /veterans-day/ content page (body + FAQs).';

    public function handle(GenerateVeteransDayPageAction $action): int
    {
        $action();

        $this->info('Seeded the /veterans-day/ content page.');

        return self::SUCCESS;
    }
}
