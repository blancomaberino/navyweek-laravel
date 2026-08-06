<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Pages\GenerateDiscountCategoryPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the `/discount/{category}/` hub pages. See
 * {@see GenerateDiscountCategoryPagesAction}.
 */
final class GenerateDiscountCategoryPagesCommand extends Command
{
    protected $signature = 'pages:generate-discount-categories';

    protected $description = 'Generate the discount category hub pages';

    public function handle(GenerateDiscountCategoryPagesAction $action): int
    {
        $this->info("Generated {$action()} discount category hub pages.");

        return self::SUCCESS;
    }
}
