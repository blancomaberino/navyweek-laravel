<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateJetTeamPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the jet-team pages (team hubs + published city guides) from the imported
 * jet-team records. Run after `import:jet-teams`. Idempotent.
 */
final class GenerateJetTeamPagesCommand extends Command
{
    protected $signature = 'pages:generate-jet-teams';

    protected $description = 'Generate the jet-team hubs (/{team}/) and city guides (/{team}/{slug}/).';

    public function handle(GenerateJetTeamPagesAction $action): int
    {
        $count = $action();

        $this->info("Generated {$count} jet-team pages.");

        return self::SUCCESS;
    }
}
