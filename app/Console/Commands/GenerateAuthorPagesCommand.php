<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateAuthorPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the `/authors/{slug}/` profile page for every editorial user with a public
 * profile slug. Idempotent — safe to re-run; it honors the build clock and preserves an
 * editor rename.
 */
final class GenerateAuthorPagesCommand extends Command
{
    protected $signature = 'pages:generate-authors';

    protected $description = 'Generate the /authors/{slug}/ profile page for every byline user.';

    public function handle(GenerateAuthorPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} author page(s).");

        return self::SUCCESS;
    }
}
