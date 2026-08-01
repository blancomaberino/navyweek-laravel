<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateContentPagesAction;
use Illuminate\Console\Command;

/**
 * Seeds the DB-driven content pages (`/privacy/`, `/terms/`, `/contact/`) with their
 * initial CMS body. Idempotent — safe to re-run; it never clobbers a page whose body an
 * editor has already set.
 */
final class GenerateContentPagesCommand extends Command
{
    protected $signature = 'pages:generate-content';

    protected $description = 'Seed the DB-driven content pages (privacy/terms/contact) with their initial editable body.';

    public function handle(GenerateContentPagesAction $action): int
    {
        $count = $action();

        $this->info("Seeded {$count} content page(s).");

        return self::SUCCESS;
    }
}
