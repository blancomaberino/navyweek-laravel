<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Pages\GenerateLocalDiscountPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the `pages` rows for the whole local-discount family — the detail pages
 * (`/discounts/{state}/{city}/{business}/`) plus the `/discounts/` root, per-state, and
 * per-city rollup hubs — from the imported local records. Idempotent: safe to re-run;
 * upserts by url_path and preserves each page's original publish date.
 */
final class GenerateLocalDiscountPagesCommand extends Command
{
    protected $signature = 'pages:generate-local-discounts';

    protected $description = 'Generate the pages for every local-business discount detail page + the rollup hubs.';

    public function handle(GenerateLocalDiscountPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} local-discount pages (detail + hubs).");

        return self::SUCCESS;
    }
}
