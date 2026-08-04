<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateNavyReferenceHubPageAction;
use Illuminate\Console\Command;

/**
 * Generates the `/navy-reference/` library landing page.
 * See {@see GenerateNavyReferenceHubPageAction}.
 */
final class GenerateNavyReferenceHubPageCommand extends Command
{
    protected $signature = 'pages:generate-navy-reference';

    protected $description = 'Generate the /navy-reference/ library landing page';

    public function handle(GenerateNavyReferenceHubPageAction $action): int
    {
        $this->info("Generated {$action()} navy reference page.");

        return self::SUCCESS;
    }
}
