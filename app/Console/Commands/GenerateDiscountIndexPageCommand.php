<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateDiscountIndexPageAction;
use Illuminate\Console\Command;

/**
 * Generates the `/discount/` directory landing page from the published discount-brand
 * pages. Run after the discount pages are built/imported. Idempotent.
 */
final class GenerateDiscountIndexPageCommand extends Command
{
    protected $signature = 'pages:generate-discount-index';

    protected $description = 'Generate the /discount/ directory landing page.';

    public function handle(GenerateDiscountIndexPageAction $action): int
    {
        $action();

        $this->info('Generated the /discount/ index page.');

        return self::SUCCESS;
    }
}
