<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Pages\GenerateLocalDiscountPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the `pages` rows for the local-business discount detail pages
 * (`/discounts/{state}/{city}/{business}/` each) from the imported local records.
 * Idempotent — safe to re-run; upserts by url_path and preserves each page's original
 * publish date. The `/discounts/` rollup hubs are a follow-up.
 */
final class GenerateLocalDiscountPagesCommand extends Command
{
    protected $signature = 'pages:generate-local-discounts';

    protected $description = 'Generate the pages for every local-business discount detail page.';

    public function handle(GenerateLocalDiscountPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} local-discount detail pages.");

        return self::SUCCESS;
    }
}
